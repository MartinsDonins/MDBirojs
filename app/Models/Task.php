<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'notes',
        'priority',
        'status',
        'task_category_id',
        'due_at',
        'recurrence_type',
        'transaction_id',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'task_category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class)->orderBy('sort_order');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at
            && $this->due_at->isPast()
            && ! in_array($this->status, ['completed', 'cancelled']);
    }

    public function completedItemsCount(): int
    {
        return $this->items->where('is_completed', true)->count();
    }

    public static function getPriorityColor(string $priority): string
    {
        return match ($priority) {
            'low'    => 'info',
            'medium' => 'warning',
            'high'   => 'danger',
            'urgent' => 'danger',
            default  => 'gray',
        };
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'open'        => 'info',
            'in_progress' => 'warning',
            'completed'   => 'success',
            'cancelled'   => 'gray',
            default       => 'gray',
        };
    }

    protected static function booted(): void
    {
        static::updating(function (Task $task) {
            if ($task->isDirty('status')) {
                if ($task->status === 'completed' && ! $task->completed_at) {
                    $task->completed_at = now();
                }
                if ($task->status !== 'completed') {
                    $task->completed_at = null;
                }
            }
        });

        static::updated(function (Task $task) {
            if (
                $task->wasChanged('status')
                && $task->status === 'completed'
                && $task->recurrence_type !== 'none'
            ) {
                static::createRecurringTask($task);
            }
        });
    }

    protected static function createRecurringTask(Task $task): void
    {
        $nextDueAt = null;

        if ($task->due_at) {
            $nextDueAt = match ($task->recurrence_type) {
                'daily'   => $task->due_at->copy()->addDay(),
                'weekly'  => $task->due_at->copy()->addWeek(),
                'monthly' => $task->due_at->copy()->addMonth(),
                'yearly'  => $task->due_at->copy()->addYear(),
                default   => null,
            };
        }

        static::create([
            'title'            => $task->title,
            'description'      => $task->description,
            'priority'         => $task->priority,
            'status'           => 'open',
            'task_category_id' => $task->task_category_id,
            'due_at'           => $nextDueAt,
            'recurrence_type'  => $task->recurrence_type,
        ]);
    }
}
