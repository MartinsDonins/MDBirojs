<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * High-level financial snapshot for the current year, shown on the dashboard.
 * Mirrors the figures used by the profit/loss report: COMPLETED transactions only,
 * amounts in EUR (amount_eur with a fallback to amount), summed by absolute value.
 */
class FinancialOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $year  = now()->year;
        $month = now()->month;

        $incomeYear  = $this->sum('INCOME', $year);
        $expenseYear = $this->sum(['EXPENSE', 'FEE'], $year);
        $profitYear  = $incomeYear - $expenseYear;

        $incomeMonth  = $this->sum('INCOME', $year, $month);
        $expenseMonth = $this->sum(['EXPENSE', 'FEE'], $year, $month);

        // Completed transactions still missing a category — the bookkeeper's to-do list.
        $uncategorized = Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereIn('type', ['INCOME', 'EXPENSE', 'FEE'])
            ->whereNull('category_id')
            ->count();

        return [
            Stat::make('Ieņēmumi '.$year, $this->money($incomeYear))
                ->description('Šomēnes: '.$this->money($incomeMonth))
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->chart($this->monthlyTrend('INCOME', $year))
                ->color('success'),

            Stat::make('Izdevumi '.$year, $this->money($expenseYear))
                ->description('Šomēnes: '.$this->money($expenseMonth))
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->chart($this->monthlyTrend(['EXPENSE', 'FEE'], $year))
                ->color('danger'),

            Stat::make('Bilance '.$year, $this->money($profitYear))
                ->description($profitYear >= 0 ? 'Peļņa' : 'Zaudējumi')
                ->descriptionIcon($profitYear >= 0 ? 'heroicon-o-banknotes' : 'heroicon-o-exclamation-triangle')
                ->color($profitYear >= 0 ? 'success' : 'danger'),

            Stat::make('Nekategorizēti', $uncategorized)
                ->description($uncategorized > 0 ? 'Jāpiešķir kategorija' : 'Viss sakārtots')
                ->descriptionIcon('heroicon-o-tag')
                ->color($uncategorized > 0 ? 'warning' : 'success')
                ->url($uncategorized > 0 ? TransactionResource::getUrl('index') : null),
        ];
    }

    /**
     * Sum absolute EUR amounts of COMPLETED transactions of the given type(s),
     * optionally constrained to a single month.
     */
    private function sum(string|array $types, int $year, ?int $month = null): float
    {
        $query = Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereIn('type', (array) $types)
            ->whereYear('occurred_at', $year);

        if ($month !== null) {
            $query->whereMonth('occurred_at', $month);
        }

        return (float) $query->sum(DB::raw('ABS(COALESCE(amount_eur, amount))'));
    }

    /** 12-month sparkline (Jan→Dec) of the given type(s) for the year. */
    private function monthlyTrend(string|array $types, int $year): array
    {
        $trend = [];
        for ($m = 1; $m <= 12; $m++) {
            $trend[] = round($this->sum($types, $year, $m), 2);
        }

        return $trend;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', ' ').' €';
    }
}
