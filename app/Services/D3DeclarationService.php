<?php

namespace App\Services;

use App\Models\JournalColumn;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Derives the journal-based figures of the VID D3 annex
 * ("Ienākumi no saimnieciskās darbības") for a given year.
 *
 * Only the rows that can be computed directly from the income/expense journal
 * are produced here:
 *   - row 5  "Ieņēmumi no citiem saimnieciskās darbības veidiem"
 *            = total of the FIRST (taxable) income column  (e.g. VID 4,5,6)
 *   - row 6  "Izdevumi, kas saistīti ar citiem saimnieciskās darbības veidiem"
 *            = total of the FIRST (deductible) expense column (e.g. VID 19–23)
 *   - row 4  "Neapliekamie ienākumi"
 *            = total of the income column(s) flagged non-taxable (e.g. VID 10)
 *
 * All other rows (farming income/expenses, prior-year losses, foreign tax,
 * minimum taxable income) are manual inputs and live in {@see \App\Models\D3Setting}.
 *
 * The mapping mirrors {@see AnnualReviewService} and {@see \App\Filament\Pages\ProfitLossReport}
 * so the three reports stay consistent: the first income/expense column is the
 * taxable / deductible one; non-taxable income is whatever income column maps to
 * VID column 10 ("Neapliekamie").
 */
class D3DeclarationService
{
    /** VID journal column number used for non-taxable income ("Neapliekamie ienākumi"). */
    private const NON_TAXABLE_VID = 10;

    /**
     * @return array{
     *   year:int,
     *   other_income:float,
     *   other_expenses:float,
     *   non_taxable_income:float,
     *   income_abbr:string,
     *   expense_abbr:string,
     *   non_taxable_abbr:string
     * }
     */
    public function build(int $year): array
    {
        $incomeCols  = JournalColumn::visibleForGroup('income');
        $expenseCols = JournalColumn::visibleForGroup('expense');

        $taxableIncomeCol     = $incomeCols->first();
        $deductibleExpenseCol = $expenseCols->first();

        $taxableIncomeVids = array_map('intval', $taxableIncomeCol->vid_columns ?? []);
        $deductibleVids    = array_map('intval', $deductibleExpenseCol->vid_columns ?? []);

        // Non-taxable income = whatever income column(s) map to the non-taxable VID column.
        $nonTaxableCol  = $incomeCols->first(
            fn ($c) => in_array(self::NON_TAXABLE_VID, array_map('intval', $c->vid_columns ?? []), true)
        );
        $nonTaxableVids = array_map('intval', $nonTaxableCol->vid_columns ?? []);

        return [
            'year'               => $year,
            'other_income'       => $this->sum($year, ['INCOME'], $taxableIncomeVids),
            'other_expenses'     => $this->sum($year, ['EXPENSE', 'FEE'], $deductibleVids),
            'non_taxable_income' => $nonTaxableCol ? $this->sum($year, ['INCOME'], $nonTaxableVids) : 0.0,
            'income_abbr'        => $taxableIncomeCol->abbr ?? 'Saimn.darb.',
            'expense_abbr'       => $deductibleExpenseCol->abbr ?? 'Saist.SD',
            'non_taxable_abbr'   => $nonTaxableCol->abbr ?? 'Neapl.',
        ];
    }

    /**
     * Years that have at least one COMPLETED income/expense transaction, descending.
     *
     * @return int[]
     */
    public function availableYears(): array
    {
        return Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereIn('type', ['INCOME', 'EXPENSE', 'FEE'])
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM occurred_at)::int AS yr')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /**
     * Sum the absolute EUR amount of COMPLETED transactions of given type(s) whose
     * category maps to one of the given VID columns, for the year.
     *
     * @param  string[]  $types
     * @param  int[]  $vidColumns
     */
    private function sum(int $year, array $types, array $vidColumns): float
    {
        if (empty($vidColumns)) {
            return 0.0;
        }

        return (float) Transaction::query()
            ->where('transactions.status', 'COMPLETED')
            ->whereIn('transactions.type', $types)
            ->whereYear('transactions.occurred_at', $year)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereIn('categories.vid_column', $vidColumns)
            ->sum(DB::raw('ABS(COALESCE(transactions.amount_eur, transactions.amount))'));
    }
}
