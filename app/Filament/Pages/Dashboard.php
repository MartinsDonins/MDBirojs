<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FinancialOverviewWidget;
use App\Filament\Widgets\IncomeExpenseChartWidget;
use App\Filament\Widgets\TaskStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

/**
 * Curated dashboard. Filament auto-discovers every widget in app/Filament/Widgets,
 * which would otherwise dump the vehicle/maintenance widgets here too. By overriding
 * getWidgets() we keep the landing page focused on what a bookkeeper needs first:
 * a financial overview, the 12-month trend and outstanding tasks.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Sākums';

    protected static ?string $navigationLabel = 'Sākums';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            FinancialOverviewWidget::class,
            TaskStatsWidget::class,
            IncomeExpenseChartWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
