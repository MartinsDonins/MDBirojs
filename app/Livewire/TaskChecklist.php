<?php

namespace App\Livewire;

use App\Models\TaskItem;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskChecklist extends Component
{
    public int $taskId;

    public array $items = [];

    public string $newTitle = '';

    public bool $addingNew = false;

    public ?int $editingId = null;

    public string $editingTitle = '';

    public function mount(int $taskId): void
    {
        $this->taskId = $taskId;
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $this->items = TaskItem::where('task_id', $this->taskId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($item) => [
                'id'           => $item->id,
                'title'        => $item->title,
                'is_completed' => (bool) $item->is_completed,
            ])
            ->toArray();
    }

    public function toggle(int $id): void
    {
        $item = TaskItem::findOrFail($id);
        $item->update(['is_completed' => ! $item->is_completed]);
        $this->loadItems();
    }

    public function startEdit(int $id): void
    {
        $found = collect($this->items)->firstWhere('id', $id);
        if ($found) {
            $this->editingId    = $id;
            $this->editingTitle = $found['title'];
        }
    }

    public function saveEdit(): void
    {
        $this->validate(['editingTitle' => 'required|max:255']);
        TaskItem::findOrFail($this->editingId)->update(['title' => $this->editingTitle]);
        $this->editingId    = null;
        $this->editingTitle = '';
        $this->loadItems();
    }

    public function cancelEdit(): void
    {
        $this->editingId    = null;
        $this->editingTitle = '';
    }

    public function delete(int $id): void
    {
        TaskItem::findOrFail($id)->delete();
        $this->loadItems();
    }

    public function addNew(): void
    {
        $this->validate(['newTitle' => 'required|max:255']);
        $maxOrder = TaskItem::where('task_id', $this->taskId)->max('sort_order') ?? 0;

        TaskItem::create([
            'task_id'      => $this->taskId,
            'title'        => $this->newTitle,
            'is_completed' => false,
            'sort_order'   => $maxOrder + 1,
        ]);

        $this->newTitle   = '';
        $this->addingNew  = false;
        $this->loadItems();
    }

    public function cancelNew(): void
    {
        $this->newTitle  = '';
        $this->addingNew = false;
    }

    public function render(): View
    {
        return view('livewire.task-checklist');
    }
}
