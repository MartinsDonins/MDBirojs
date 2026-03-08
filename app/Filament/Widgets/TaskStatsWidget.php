<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaskStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $activeStatuses = ['open', 'in_progress'];

        $total    = Task::whereIn('status', $activeStatuses)->count();
        $today    = Task::whereIn('status', $activeStatuses)->whereDate('due_at', today())->count();
        $overdue  = Task::whereIn('status', $activeStatuses)->where('due_at', '<', now())->whereDate('due_at', '<', today())->count();
        $done     = Task::where('status', 'completed')->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count();

        return [
            Stat::make('Aktīvie uzdevumi', $total)
                ->description('Atvērti un procesā')
                ->descriptionIcon('heroicon-o-clipboard-document-list')
                ->color($total > 0 ? 'info' : 'gray'),

            Stat::make('Termiņš šodien', $today)
                ->description('Jāpabeidz līdz vakaram')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color($today > 0 ? 'warning' : 'success'),

            Stat::make('Nokavēti', $overdue)
                ->description('Pārsniedzis termiņu')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'success'),

            Stat::make('Pabeigti šomēnesī', $done)
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),
        ];
    }
}
