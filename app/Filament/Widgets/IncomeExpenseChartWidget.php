<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Income vs. expense over the last 12 months (COMPLETED transactions, EUR).
 * Gives the dashboard a quick visual read of cash-flow trends.
 */
class IncomeExpenseChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ieņēmumi un izdevumi (12 mēneši)';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $months = collect(range(11, 0))
            ->map(fn (int $i) => Carbon::today()->startOfMonth()->subMonths($i));

        $labels = $months->map(fn (Carbon $m) => $m->translatedFormat('M Y'))->toArray();

        $income  = $months->map(fn (Carbon $m) => $this->sum('INCOME', $m))->toArray();
        $expense = $months->map(fn (Carbon $m) => $this->sum(['EXPENSE', 'FEE'], $m))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Ieņēmumi',
                    'data' => $income,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.7)',
                    'borderColor' => 'rgb(16, 185, 129)',
                ],
                [
                    'label' => 'Izdevumi',
                    'data' => $expense,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
                    'borderColor' => 'rgb(239, 68, 68)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function sum(string|array $types, Carbon $month): float
    {
        return round((float) Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereIn('type', (array) $types)
            ->whereYear('occurred_at', $month->year)
            ->whereMonth('occurred_at', $month->month)
            ->sum(DB::raw('ABS(COALESCE(amount_eur, amount))')), 2);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
