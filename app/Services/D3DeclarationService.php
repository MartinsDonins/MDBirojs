<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\D3Setting;
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
     * Full D3 report for the year: journal-derived auto figures + saved manual
     * inputs + computed totals + taxpayer header. Used by the PDF export.
     *
     * @return array<string,mixed>
     */
    public function fullReport(int $year): array
    {
        $auto = $this->build($year);
        $s    = D3Setting::firstOrNew(['year' => $year]);

        $manual = [
            'farm_1_1'  => (float) ($s->farm_income_agriculture ?? 0),
            'farm_1_2'  => (float) ($s->farm_income_fishery ?? 0),
            'farm_1_3'  => (float) ($s->farm_income_tourism ?? 0),
            'farm_1_4'  => (float) ($s->farm_income_support ?? 0),
            'farm_2'    => (float) ($s->farm_expenses ?? 0),
            'farm_3'    => (float) ($s->farm_prior_losses ?? 0),
            'other_7'   => (float) ($s->other_prior_losses ?? 0),
            'foreign_9' => (float) ($s->foreign_tax ?? 0),
            'min_10'    => (float) ($s->min_taxable_income ?? 0),
        ];

        return [
            'year'           => $year,
            'rows'           => self::computeRows($auto, $manual),
            'income_abbr'    => $auto['income_abbr'],
            'expense_abbr'   => $auto['expense_abbr'],
            'non_taxable_abbr' => $auto['non_taxable_abbr'],
            'taxpayer_name'  => AppSetting::getRaw('taxpayer_name'),
            'taxpayer_code'  => AppSetting::getRaw('taxpayer_code'),
        ];
    }

    /**
     * Compute the full D3 row set from journal-derived auto figures and manual inputs.
     * Single source of truth shared by the Filament page and the PDF.
     *
     * @param  array{other_income:float,other_expenses:float,non_taxable_income:float}  $auto
     * @param  array<string,float>  $manual
     * @return array<string,float>
     */
    public static function computeRows(array $auto, array $manual): array
    {
        $row1_1 = $manual['farm_1_1'] ?? 0.0;
        $row1_2 = $manual['farm_1_2'] ?? 0.0;
        $row1_3 = $manual['farm_1_3'] ?? 0.0;
        $row1_4 = $manual['farm_1_4'] ?? 0.0;
        $row1   = $row1_1 + $row1_2 + $row1_3 + $row1_4;
        $row2   = $manual['farm_2'] ?? 0.0;
        $row3   = $manual['farm_3'] ?? 0.0;
        $row4   = $auto['non_taxable_income'];
        $row5   = $auto['other_income'];
        $row6   = $auto['other_expenses'];
        $row7   = $manual['other_7'] ?? 0.0;

        // 8 = (farming bracket) + (other-activity bracket).
        // Non-taxable income (row 4) is shown for completeness; the taxable income
        // column (row 5) in this journal already excludes it, so it does not reduce
        // the result here.
        $row8  = ($row1 - $row1_4 - $row2 - $row3) + ($row5 - $row6 - $row7);
        $row9  = $manual['foreign_9'] ?? 0.0;
        $row10 = $manual['min_10'] ?? 0.0;
        $row11 = $row8 - $row10;

        return compact(
            'row1_1', 'row1_2', 'row1_3', 'row1_4', 'row1',
            'row2', 'row3', 'row4', 'row5', 'row6', 'row7',
            'row8', 'row9', 'row10', 'row11',
        );
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
