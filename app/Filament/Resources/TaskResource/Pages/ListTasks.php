<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Filament\Widgets\TaskStatsWidget;
use App\Models\Task;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected static string $view = 'filament.resources.task-resource.pages.list-tasks';

    public string $activeView = 'list';

    public function setActiveView(string $view): void
    {
        $this->activeView = $view;
    }

    public function getKanbanData(): array
    {
        $statuses = ['open', 'in_progress', 'completed', 'cancelled'];
        $data = [];

        foreach ($statuses as $status) {
            $query = Task::where('status', $status)->with(['category', 'items']);

            if ($status === 'completed') {
                $query->orderByDesc('completed_at')->limit(20);
            } else {
                $query->orderByRaw('due_at IS NULL, due_at ASC');
            }

            $data[$status] = $query->get();
        }

        return $data;
    }

    public function updateTaskStatus(int $taskId, string $newStatus): void
    {
        $task = Task::findOrFail($taskId);
        $task->update(['status' => $newStatus]);
        $this->dispatch('task-updated');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TaskStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Jauns uzdevums'),
        ];
    }
}
