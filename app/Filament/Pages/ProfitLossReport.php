<?php

namespace App\Filament\Pages;

use App\Models\JournalColumn;
use App\Models\ProfitLossSetting;
use App\Models\Transaction;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProfitLossReport extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Nodokļu aprēķins';
    protected static ?string $title           = 'Nodokļu aprēķins';
    protected static string $view             = 'filament.pages.profit-loss-report';
    protected ?string $maxContentWidth        = 'full';
    protected static ?int $navigationSort     = 7;

    /** Yearly summary rows (descending by year for table display) */
    public array $yearlyData    = [];
    /** Monthly breakdown: [year => [1..12 => [name, income, expense, profit, cumulative, vsaa]]] */
    public array $monthlyData   = [];
    /** Years currently expanded in the table */
    public array $expandedYears = [];

    /** Starting balance (European format string, e.g. "1 234,56") */
    public string $startingBalance = '0';

    // ── Per-year tax/VSAA parameters (keyed by year integer, stored as strings for wire:model) ──

    /** IIN rates: [year => '23.00'] */
    public array $taxRates         = [];
    /** Minimālā alga €/mēn.: [year => '700.00'] */
    public array $minWages         = [];
    /** VSAA pilnā likme %: [year => '31.07'] */
    public array $vsaaFullRates    = [];
    /** VSAA samazinātā likme %: [year => '10.00'] */
    public array $vsaaReducedRates = [];

    /** Abbreviation of the first income JournalColumn */
    public string $incomeAbbr  = '';
    /** Abbreviation of the first expense JournalColumn */
    public string $expenseAbbr = '';

    // ──────────────────────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->loadData();
    }

    // ──────────────────────────────────────────────────────────────
    // Livewire updated hooks — save to DB, recompute
    // ──────────────────────────────────────────────────────────────

    public function updatedStartingBalance(): void
    {
        $this->computeCumulativeBalances();
    }

    public function updatedTaxRates(string $value, string $key): void
    {
        $year = (int) $key;
        $rate = max(0.0, (float) str_replace(',', '.', $value));
        ProfitLossSetting::updateOrCreate(['year' => $year], ['tax_rate' => $rate]);
        $this->taxRates[$year] = number_format($rate, 2, '.', '');
        $this->computeCumulativeBalances();
    }

    public function updatedMinWages(string $value, string $key): void
    {
        $year  = (int) $key;
        $wage  = max(0.0, (float) str_replace(',', '.', $value));
        ProfitLossSetting::updateOrCreate(['year' => $year], ['min_wage' => $wage]);
        $this->minWages[$year] = number_format($wage, 2, '.', '');
        $this->computeCumulativeBalances();
    }

    public function updatedVsaaFullRates(string $value, string $key): void
    {
        $year = (int) $key;
        $rate = max(0.0, (float) str_replace(',', '.', $value));
        ProfitLossSetting::updateOrCreate(['year' => $year], ['vsaa_full_rate' => $rate]);
        $this->vsaaFullRates[$year] = number_format($rate, 2, '.', '');
        $this->computeCumulativeBalances();
    }

    public function updatedVsaaReducedRates(string $value, string $key): void
    {
        $year = (int) $key;
        $rate = max(0.0, (float) str_replace(',', '.', $value));
        ProfitLossSetting::updateOrCreate(['year' => $year], ['vsaa_reduced_rate' => $rate]);
        $this->vsaaReducedRates[$year] = number_format($rate, 2, '.', '');
        $this->computeCumulativeBalances();
    }

    public function toggleYear(int $year): void
    {
        if (in_array($year, $this->expandedYears)) {
            $this->expandedYears = array_values(array_diff($this->expandedYears, [$year]));
        } else {
            $this->expandedYears[] = $year;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Data loading (runs once on mount)
    // ──────────────────────────────────────────────────────────────

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

        // Table: newest year first
        $this->yearlyData = array_reverse($yearlyAsc);

        // Load saved settings from DB for all present years
        $years       = array_column($yearlyAsc, 'year');
        $savedByYear = ProfitLossSetting::whereIn('year', $years)
            ->get()
            ->keyBy('year');

        foreach ($years as $year) {
            $s = $savedByYear->get($year);
            $this->taxRates[$year]         = $s ? (string) $s->tax_rate         : '23.00';
            $this->minWages[$year]         = $s ? (string) $s->min_wage         : '700.00';
            $this->vsaaFullRates[$year]    = $s ? (string) $s->vsaa_full_rate   : '31.07';
            $this->vsaaReducedRates[$year] = $s ? (string) $s->vsaa_reduced_rate : '10.00';
        }

        $this->computeCumulativeBalances();
    }

    // ──────────────────────────────────────────────────────────────
    // Core computation (re-runs on any parameter change)
    // ──────────────────────────────────────────────────────────────

    /**
     * Walk years oldest→newest:
     *  - accumulate cumulative balance (starting balance + yearly profits)
     *  - compute IIN tax per year
     *  - compute VSAA per month (formula with min_wage threshold), then sum per year
     */
    private function computeCumulativeBalances(): void
    {
        $start = $this->parseStartingBalance();

        $ascending = array_reverse($this->yearlyData);
        $running   = $start;
        $result    = [];

        foreach ($ascending as $yr) {
            $yearOpening = $running;
            $running    += $yr['profit'];

            $yr['cumulative']   = $running;
            $yr['year_opening'] = $yearOpening;

            // IIN (only when profit > 0)
            $taxRate          = (float) ($this->taxRates[$yr['year']] ?? 23.0);
            $yr['tax_rate']   = $taxRate;
            $yr['tax_amount'] = $yr['profit'] > 0
                ? round($yr['profit'] * $taxRate / 100, 2)
                : 0.0;

            // VSAA parameters for this year
            $minWage      = (float) ($this->minWages[$yr['year']]         ?? 700.0);
            $vsaaFull     = (float) ($this->vsaaFullRates[$yr['year']]    ?? 31.07);
            $vsaaReduced  = (float) ($this->vsaaReducedRates[$yr['year']] ?? 10.0);

            $yr['min_wage']          = $minWage;
            $yr['vsaa_full_rate']    = $vsaaFull;
            $yr['vsaa_reduced_rate'] = $vsaaReduced;

            // Monthly cumulative + monthly VSAA
            $yearVsaa = 0.0;
            if (isset($this->monthlyData[$yr['year']])) {
                $mRunning = $yearOpening;
                foreach ($this->monthlyData[$yr['year']] as $m => $mData) {
                    $mProfit  = $mData['profit'];
                    $mRunning += $mProfit;
                    $this->monthlyData[$yr['year']][$m]['cumulative'] = $mRunning;

                    // VSAA formula (based on monthly profit):
                    //   profit ≤ 0           → VSAA = 0
                    //   profit < min_wage    → VSAA = profit × reduced%
                    //   profit ≥ min_wage    → VSAA = min_wage × full% + (profit − min_wage) × reduced%
                    if ($mProfit <= 0) {
                        $mVsaa = 0.0;
                    } elseif ($mProfit < $minWage) {
                        $mVsaa = round($mProfit * $vsaaReduced / 100, 2);
                    } else {
                        $mVsaa = round(
                            $minWage * $vsaaFull / 100 + ($mProfit - $minWage) * $vsaaReduced / 100,
                            2
                        );
                    }
                    $this->monthlyData[$yr['year']][$m]['vsaa'] = $mVsaa;
                    $yearVsaa += $mVsaa;
                }
            }
            $yr['vsaa_amount'] = round($yearVsaa, 2);

            $result[] = $yr;
        }

        $this->yearlyData = array_reverse($result);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /** Parse user-entered balance: handles "1.234,56" and "1234.56" */
    private function parseStartingBalance(): float
    {
        $val = trim($this->startingBalance);
        $val = str_replace([' ', "\xc2\xa0"], '', $val);
        if (str_contains($val, ',')) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        }
        return (float) $val;
    }

    /**
     * Sum breakdown rows matching given type(s) and vid_columns.
     * Empty $vidColumns → include all (no vid filter).
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
}
