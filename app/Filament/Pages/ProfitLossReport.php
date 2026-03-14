<?php

namespace App\Filament\Pages;

use App\Models\JournalColumn;
use App\Models\ProfitLossSetting;
use App\Models\Transaction;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProfitLossReport extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'P&L Aprēķins';
    protected static ?string $title           = 'Peļņas / Zaudējumu Aprēķins';
    protected static string $view             = 'filament.pages.profit-loss-report';
    protected ?string $maxContentWidth        = 'full';
    protected static ?int $navigationSort     = 7;

    /** Yearly summary rows (descending by year for table) */
    public array $yearlyData   = [];
    /** Monthly breakdown: [year => [1..12 => [name, income, expense, profit]]] */
    public array $monthlyData  = [];
    /** Years currently expanded in the table */
    public array $expandedYears = [];
    /** Starting balance entered by the user (European format string, e.g. "1 234,56") */
    public string $startingBalance = '0';
    /**
     * Tax rates per year, keyed by year integer.
     * Loaded from DB; updated via updatedTaxRates() lifecycle hook.
     * e.g. [2024 => '23.00', 2025 => '20.00']
     */
    public array $taxRates = [];
    /** Abbreviation of the first income JournalColumn */
    public string $incomeAbbr  = '';
    /** Abbreviation of the first expense JournalColumn */
    public string $expenseAbbr = '';

    public function mount(): void
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $incomeCol  = JournalColumn::visibleForGroup('income')->first();
        $expenseCol = JournalColumn::visibleForGroup('expense')->first();

        $this->incomeAbbr  = $incomeCol?->abbr  ?? 'Saimn.darb.';
        $this->expenseAbbr = $expenseCol?->abbr  ?? 'Saist.SD.';

        $incomeVids  = array_map('intval', $incomeCol?->vid_columns  ?? []);
        $expenseVids = array_map('intval', $expenseCol?->vid_columns ?? []);

        // Single query: year + month breakdown by type + vid_column (COMPLETED only)
        $breakdown = Transaction::query()
            ->where('transactions.status', 'COMPLETED')
            ->whereIn('transactions.type', ['INCOME', 'EXPENSE', 'FEE'])
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("
                EXTRACT(YEAR  FROM transactions.occurred_at) AS yr,
                EXTRACT(MONTH FROM transactions.occurred_at) AS mo,
                transactions.type,
                COALESCE(categories.vid_column, 0)           AS vid_col,
                SUM(ABS(COALESCE(transactions.amount_eur, transactions.amount))) AS total
            ")
            ->groupBy(
                DB::raw('EXTRACT(YEAR  FROM transactions.occurred_at)'),
                DB::raw('EXTRACT(MONTH FROM transactions.occurred_at)'),
                'transactions.type',
                'categories.vid_column'
            )
            ->get();

        $allYears = $breakdown->pluck('yr')->unique()->sort()->values();

        $monthNames = [
            1  => 'Janvāris',   2  => 'Februāris', 3  => 'Marts',
            4  => 'Aprīlis',    5  => 'Maijs',      6  => 'Jūnijs',
            7  => 'Jūlijs',     8  => 'Augusts',    9  => 'Septembris',
            10 => 'Oktobris',   11 => 'Novembris',  12 => 'Decembris',
        ];

        $yearlyAsc         = [];
        $this->monthlyData = [];

        foreach ($allYears as $yr) {
            $year     = (int) round((float) $yr);
            $yearRows = $breakdown->filter(fn ($r) => (int) round((float) $r->yr) === $year);

            $yearIncome  = $this->sumFor($yearRows, 'INCOME',           $incomeVids);
            $yearExpense = $this->sumFor($yearRows, ['EXPENSE', 'FEE'], $expenseVids);

            $yearlyAsc[] = [
                'year'    => $year,
                'income'  => $yearIncome,
                'expense' => $yearExpense,
                'profit'  => $yearIncome - $yearExpense,
            ];

            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $mRows = $yearRows->filter(fn ($r) => (int) round((float) $r->mo) === $m);
                $mInc  = $this->sumFor($mRows, 'INCOME',           $incomeVids);
                $mExp  = $this->sumFor($mRows, ['EXPENSE', 'FEE'], $expenseVids);
                $months[$m] = [
                    'name'    => $monthNames[$m],
                    'income'  => $mInc,
                    'expense' => $mExp,
                    'profit'  => $mInc - $mExp,
                ];
            }
            $this->monthlyData[$year] = $months;
        }

        // Table: newest year first; charts use ascending order (reversed in JS)
        $this->yearlyData = array_reverse($yearlyAsc);

        // Load saved tax rates from DB (default 23% for missing years)
        $savedRates = ProfitLossSetting::whereIn('year', array_column($yearlyAsc, 'year'))
            ->pluck('tax_rate', 'year')
            ->toArray();

        foreach ($yearlyAsc as $row) {
            $this->taxRates[$row['year']] = isset($savedRates[$row['year']])
                ? (string) $savedRates[$row['year']]
                : '23.00';
        }

        $this->computeCumulativeBalances();
    }

    /**
     * Re-compute cumulative running balances when startingBalance changes.
     * Called by Livewire's lifecycle hook on wire:model update.
     */
    public function updatedStartingBalance(): void
    {
        $this->computeCumulativeBalances();
    }

    /**
     * Persist the tax rate for a specific year and recompute.
     * Called by Livewire's lifecycle hook when any $taxRates[year] changes via wire:model.blur.
     * $key = the year (e.g. '2024'), $value = the new rate string.
     */
    public function updatedTaxRates(string $value, string $key): void
    {
        $year = (int) $key;
        $rate = max(0.0, (float) str_replace(',', '.', $value));

        ProfitLossSetting::updateOrCreate(
            ['year'     => $year],
            ['tax_rate' => $rate]
        );

        // Normalise the stored string to 2 decimals
        $this->taxRates[$year] = number_format($rate, 2, '.', '');

        $this->computeCumulativeBalances();
    }

    /**
     * Walk years oldest→newest, accumulate profit + starting balance.
     * Stores 'cumulative' on each yearlyData row and on each monthlyData month row.
     */
    private function computeCumulativeBalances(): void
    {
        $start = $this->parseStartingBalance();

        // yearlyData is descending; iterate ascending
        $ascending = array_reverse($this->yearlyData);
        $running   = $start;
        $result    = [];

        foreach ($ascending as $yr) {
            $yearOpening = $running;
            $running    += $yr['profit'];
            $yr['cumulative']   = $running;
            $yr['year_opening'] = $yearOpening;

            // IIN: only on positive annual profit; zero if loss
            $taxRate              = (float) ($this->taxRates[$yr['year']] ?? 23.0);
            $yr['tax_rate']       = $taxRate;
            $yr['tax_amount']     = $yr['profit'] > 0
                ? round($yr['profit'] * $taxRate / 100, 2)
                : 0.0;

            $result[] = $yr;

            // Monthly cumulative: opening balance of the year + running monthly sum
            if (isset($this->monthlyData[$yr['year']])) {
                $mRunning = $yearOpening;
                foreach ($this->monthlyData[$yr['year']] as $m => $mData) {
                    $mRunning += $mData['profit'];
                    $this->monthlyData[$yr['year']][$m]['cumulative'] = $mRunning;
                }
            }
        }

        $this->yearlyData = array_reverse($result);
    }

    /**
     * Parse user-entered balance string: handles European (1.234,56) and standard (1234.56) formats.
     */
    private function parseStartingBalance(): float
    {
        $val = trim($this->startingBalance);
        $val = str_replace([' ', "\xc2\xa0"], '', $val); // remove space + NBSP

        if (str_contains($val, ',')) {
            $val = str_replace('.', '', $val);  // remove thousands dots
            $val = str_replace(',', '.', $val); // comma → period
        }

        return (float) $val;
    }

    /**
     * Sum breakdown rows matching the given transaction type(s) and vid_columns.
     * If $vidColumns is empty → include all rows for the given types (no vid filter).
     */
    private function sumFor($rows, string|array $types, array $vidColumns): float
    {
        $typesArr = (array) $types;
        return (float) $rows
            ->filter(function ($r) use ($typesArr, $vidColumns) {
                if (!in_array($r->type, $typesArr)) {
                    return false;
                }
                if (empty($vidColumns)) {
                    return true;
                }
                return in_array((int) $r->vid_col, $vidColumns);
            })
            ->sum('total');
    }

    public function toggleYear(int $year): void
    {
        if (in_array($year, $this->expandedYears)) {
            $this->expandedYears = array_values(array_diff($this->expandedYears, [$year]));
        } else {
            $this->expandedYears[] = $year;
        }
    }
}
