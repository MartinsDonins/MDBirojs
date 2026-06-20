<?php

namespace App\Services;

use App\Models\JournalColumn;
use App\Models\Transaction;

/**
 * Builds the "annual review" dataset — every transaction of a year classified
 * the same way the tax report / journal does (income, deductible expense,
 * non-deductible expense, transfer), plus a counter-transaction reconciliation
 * for transfers and a list of un-mapped income/expense rows.
 *
 * Used by both the PDF and the Excel export so the two stay in sync.
 */
class AnnualReviewService
{
    /**
     * @return array{
     *   year:int,
     *   rows:array<int,array<string,mixed>>,
     *   summary:array<string,float|int>,
     *   transfers:array<int,array<string,mixed>>,
     *   unmapped:array<int,array<string,mixed>>
     * }
     */
    public function build(int $year): array
    {
        $incomeCols  = JournalColumn::visibleForGroup('income');
        $expenseCols = JournalColumn::visibleForGroup('expense');

        // First income column = taxable business income ("saimnieciskās darbības ieņēmumi").
        $incomeDeductibleVids = array_map('intval', $incomeCols->first()->vid_columns ?? []);
        // First expense column = deductible ("Saist.SD"); the rest are non-deductible.
        $deductibleExpenseVids = array_map('intval', $expenseCols->first()->vid_columns ?? []);
        $nonDeductibleVids = $expenseCols->slice(1)
            ->flatMap(fn ($c) => array_map('intval', $c->vid_columns ?? []))
            ->unique()
            ->values()
            ->all();

        // vid_column number → column abbreviation, for precise labels.
        $vidToAbbr = [];
        foreach ($incomeCols->concat($expenseCols) as $c) {
            foreach (array_map('intval', $c->vid_columns ?? []) as $v) {
                $vidToAbbr[$v] = $c->abbr;
            }
        }

        $transactions = Transaction::with(['account', 'category', 'linkedTransaction.account'])
            ->whereYear('occurred_at', $year)
            ->orderBy('occurred_at')
            ->orderByRaw('COALESCE(sort_order, 999999)')
            ->orderBy('id')
            ->get();

        $rows      = [];
        $transfers = [];
        $unmapped  = [];
        $ignored   = [];
        $summary   = [
            'income'                => 0.0,
            'expense_deductible'    => 0.0,
            'expense_nondeductible' => 0.0,
            'transfer_count'        => 0,
            'transfer_unmatched'    => 0,
            'unmapped_count'        => 0,
            'ignored_count'         => 0,
            'count'                 => 0,
        ];

        $n = 0;
        foreach ($transactions as $t) {
            // IGNORED = intentionally excluded from ALL data (income, expense, deductible,
            // non-deductible, balance). Listed separately for transparency only.
            if ($t->status === 'IGNORED') {
                $ignored[] = [
                    'date'        => $t->occurred_at->format('d.m.Y'),
                    'account'     => $t->account?->name ?? '—',
                    'partner'     => $t->counterparty_name,
                    'description' => $t->description,
                    'amount'      => abs((float) ($t->amount_eur ?? $t->amount)),
                    'currency'    => $t->currency ?? 'EUR',
                ];
                $summary['ignored_count']++;
                continue;
            }

            $n++;
            $vid       = (int) ($t->category?->vid_column ?? 0);
            $amountEur = (float) ($t->amount_eur ?? $t->amount);
            $absEur    = abs($amountEur);

            [$kind, $label] = $this->classify(
                $t->type,
                $vid,
                $incomeDeductibleVids,
                $deductibleExpenseVids,
                $nonDeductibleVids,
                $vidToAbbr,
            );

            // Signed amount for display: income/transfer-in positive, expense/transfer-out negative.
            $signed = match ($t->type) {
                'INCOME'   => $absEur,
                'TRANSFER' => $amountEur, // already signed: +in / −out
                default    => -$absEur,   // EXPENSE, FEE
            };

            $counterStatus  = null;
            $counterAccount = null;

            if ($t->type === 'TRANSFER') {
                $matched        = $t->linked_transaction_id !== null && $t->linkedTransaction !== null;
                $counterStatus  = $matched;
                $counterAccount = $t->linkedTransaction?->account?->name;

                $summary['transfer_count']++;
                if (! $matched) {
                    $summary['transfer_unmatched']++;
                }

                $transfers[] = [
                    'n'               => $n,
                    'date'            => $t->occurred_at->format('d.m.Y'),
                    'account'         => $t->account?->name ?? '—',
                    'amount'          => $amountEur,
                    'direction'       => $amountEur >= 0 ? 'Ienāk' : 'Iziet',
                    'matched'         => $matched,
                    'counter_account' => $counterAccount,
                    'description'     => $t->description,
                ];
            }

            switch ($kind) {
                case 'income':                $summary['income']                += $absEur; break;
                case 'expense_deductible':    $summary['expense_deductible']    += $absEur; break;
                case 'expense_nondeductible': $summary['expense_nondeductible'] += $absEur; break;
            }

            $isUnmapped = in_array($kind, ['income_unmapped', 'expense_unmapped'], true);

            $row = [
                'n'               => $n,
                'date'            => $t->occurred_at->format('d.m.Y'),
                'account'         => $t->account?->name ?? '—',
                'partner'         => $t->counterparty_name,
                'description'     => $t->description,
                'category'        => $t->category?->name,
                'type'            => $t->type,
                'kind'            => $kind,
                'label'           => $label,
                'amount'          => $signed,
                'amount_abs'      => $absEur,
                'currency'        => $t->currency ?? 'EUR',
                'amount_original' => (float) $t->amount,
                'status'          => $t->status,
                'counter_status'  => $counterStatus,
                'counter_account' => $counterAccount,
                'is_unmapped'     => $isUnmapped,
            ];
            $rows[] = $row;

            if ($isUnmapped) {
                $summary['unmapped_count']++;
                $unmapped[] = $row;
            }
        }

        $summary['count']  = $n;
        $summary['profit'] = $summary['income'] - $summary['expense_deductible'];

        return compact('year', 'rows', 'summary', 'transfers', 'unmapped', 'ignored');
    }

    /**
     * Classify a transaction → [kind, human label].
     *
     * @param  int[]  $incomeDeductibleVids
     * @param  int[]  $deductibleExpenseVids
     * @param  int[]  $nonDeductibleVids
     * @param  array<int,string>  $vidToAbbr
     * @return array{0:string,1:string}
     */
    private function classify(
        string $type,
        int $vid,
        array $incomeDeductibleVids,
        array $deductibleExpenseVids,
        array $nonDeductibleVids,
        array $vidToAbbr,
    ): array {
        if ($type === 'TRANSFER') {
            return ['transfer', 'Pārskaitījums'];
        }

        if ($type === 'INCOME') {
            if ($vid > 0 && in_array($vid, $incomeDeductibleVids, true)) {
                return ['income', 'Ieņēmumi (saimn.darb.)'];
            }
            if ($vid > 0) {
                return ['income', 'Ieņēmumi (' . ($vidToAbbr[$vid] ?? 'cits') . ')'];
            }
            return ['income_unmapped', 'Ieņēmumi — NAV kartēts ⚠'];
        }

        // EXPENSE or FEE
        $prefix = $type === 'FEE' ? 'Komisija' : 'Izdevumi';
        if ($vid > 0 && in_array($vid, $deductibleExpenseVids, true)) {
            return ['expense_deductible', $prefix . ' (attaisnotie)'];
        }
        if ($vid > 0 && in_array($vid, $nonDeductibleVids, true)) {
            return ['expense_nondeductible', $prefix . ' (neattaisnotie)'];
        }
        if ($vid > 0) {
            return ['expense_nondeductible', $prefix . ' (' . ($vidToAbbr[$vid] ?? 'cits') . ')'];
        }
        return ['expense_unmapped', $prefix . ' — NAV kartēts ⚠'];
    }
}
