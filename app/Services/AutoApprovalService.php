<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Rule;
use Illuminate\Support\Facades\Log;

class AutoApprovalService
{
    /**
     * Process transaction and apply auto-approval rules.
     *
     * @param Transaction $transaction
     * @return bool True if auto-approved
     */
    public function processTransaction(Transaction $transaction): bool
    {
        // Skip if already completed
        if ($transaction->status === 'COMPLETED') {
            return false;
        }

        // Try each rule in order
        if ($this->isInterAccountTransfer($transaction)) {
            $this->applyRuleAndApprove($transaction, 'inter_account_transfer');
            return true;
        }

        if ($this->isCashTransaction($transaction)) {
            $this->applyRuleAndApprove($transaction, 'cash_transaction');
            return true;
        }

        if ($this->isBankFee($transaction)) {
            $this->applyRuleAndApprove($transaction, 'bank_fee');
            return true;
        }

        if ($similarTransaction = $this->findSimilarTransaction($transaction)) {
            $this->applyRuleAndApprove($transaction, 'similar_transaction', $similarTransaction);
            return true;
        }

        return false;
    }

    /**
     * Check if transaction is a transfer between own accounts.
     */
    protected function isInterAccountTransfer(Transaction $transaction): bool
    {
        if (!$transaction->counterparty_account) {
            return false;
        }

        // Get all user's account numbers
        $userAccounts = Account::whereNotNull('account_number')
            ->pluck('account_number')
            ->toArray();

        // Check if counterparty account matches any user account
        return in_array($transaction->counterparty_account, $userAccounts);
    }

    /**
     * Check if transaction is related to cash.
     */
    protected function isCashTransaction(Transaction $transaction): bool
    {
        $cashKeywords = ['kase', 'kasē', 'cash', 'skaidra nauda', 'skaidrā naudā', 'bankomāt', 'atm'];
        
        $description = strtolower($transaction->description ?? '');
        $counterparty = strtolower($transaction->counterparty_name ?? '');
        
        foreach ($cashKeywords as $keyword) {
            if (str_contains($description, $keyword) || str_contains($counterparty, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Find similar previous transaction.
     */
    protected function findSimilarTransaction(Transaction $transaction): ?Transaction
    {
        if (!$transaction->counterparty_name) {
            return null;
        }

        // Look for completed transactions with same counterparty
        $similar = Transaction::where('status', 'COMPLETED')
            ->where('counterparty_name', $transaction->counterparty_name)
            ->where('type', $transaction->type)
            ->where('id', '!=', $transaction->id)
            ->orderBy('occurred_at', 'desc')
            ->first();

        if (!$similar) {
            return null;
        }

        // Check if amount is similar (within 20% difference)
        $amountDiff = abs($transaction->amount - $similar->amount);
        $amountThreshold = abs($similar->amount) * 0.2;

        if ($amountDiff <= $amountThreshold) {
            return $similar;
        }

        // Check if it's exact same amount (recurring payment)
        if ($transaction->amount == $similar->amount) {
            return $similar;
        }

        return null;
    }

    /**
     * Apply rule and approve transaction.
     */
    protected function applyRuleAndApprove(
        Transaction $transaction,
        string $rule,
        ?Transaction $similarTransaction = null
    ): void {
        switch ($rule) {
            case 'inter_account_transfer':
                $transaction->status = 'COMPLETED';
                $transaction->type = 'TRANSFER';
                
                // Try to find or create "Pārskaitījumi" category
                $category = \App\Models\Category::firstOrCreate(
                    ['name' => 'Pārskaitījumi starp kontiem'],
                    ['type' => 'TRANSFER']
                );
                $transaction->category_id = $category->id;
                break;

            case 'cash_transaction':
                $transaction->status = 'COMPLETED';
                
                // Try to find or create "Skaidra nauda" category
                $category = \App\Models\Category::firstOrCreate(
                    ['name' => 'Skaidra nauda'],
                    ['type' => $transaction->type]
                );
                $transaction->category_id = $category->id;
                break;

            case 'bank_fee':
                $transaction->status = 'COMPLETED';
                $transaction->type = 'EXPENSE'; // Ensure it's expense
                
                $category = \App\Models\Category::firstOrCreate(
                    ['name' => 'Bankas komisijas'],
                    ['type' => 'EXPENSE'] // Create as Expense category
                );
                $transaction->category_id = $category->id;
                break;

            case 'similar_transaction':
                if ($similarTransaction && $similarTransaction->category_id) {
                    $transaction->status = 'COMPLETED';
                    $transaction->category_id = $similarTransaction->category_id;
                }
                break;
        }

        $transaction->save();

        Log::info("Auto-approved transaction", [
            'transaction_id' => $transaction->id,
            'rule' => $rule,
            'counterparty' => $transaction->counterparty_name,
        ]);
    }

    /**
     * Apply a custom rule (from the rules table) to all non-completed transactions.
     */
    public function applyCustomRule(Rule $rule): array
    {
        $stats = ['processed' => 0, 'applied' => 0];

        $criteria = $rule->criteria ?? [];
        if (empty($criteria)) {
            return $stats;
        }

        // Eager-load account so account_name criterion works without N+1 queries
        foreach (Transaction::whereIn('status', ['DRAFT', 'NEEDS_REVIEW'])->with('account')->cursor() as $transaction) {
            $stats['processed']++;
            if ($this->matchesCriteria($transaction, $criteria)) {
                $this->applyCustomRuleAction($transaction, $rule);
                $stats['applied']++;
            }
        }

        return $stats;
    }

    /**
     * Match a transaction against a criteria structure.
     *
     * Supports two formats:
     *   - New: ['and_criteria' => [...], 'or_criteria' => [...]]
     *     AND: all and_criteria must match.
     *     OR:  if or_criteria is non-empty, at least one must match.
     *   - Legacy flat array: all items treated as AND criteria.
     */
    protected function matchesCriteria(Transaction $transaction, array $criteria): bool
    {
        if (empty($criteria)) {
            return false;
        }

        // --- New AND/OR format ---
        if (isset($criteria['and_criteria']) || isset($criteria['or_criteria'])) {
            $andList = $criteria['and_criteria'] ?? [];
            $orList  = $criteria['or_criteria']  ?? [];

            if (empty($andList) && empty($orList)) {
                return false;
            }

            // Every AND criterion must match
            foreach ($andList as $criterion) {
                if (!$this->matchesCriterion($transaction, $criterion)) {
                    return false;
                }
            }

            // If OR criteria exist, at least one must match
            if (!empty($orList)) {
                $anyMatch = false;
                foreach ($orList as $criterion) {
                    if ($this->matchesCriterion($transaction, $criterion)) {
                        $anyMatch = true;
                        break;
                    }
                }
                if (!$anyMatch) {
                    return false;
                }
            }

            return true;
        }

        // --- Legacy flat array: all treated as AND ---
        foreach ($criteria as $criterion) {
            if (!$this->matchesCriterion($transaction, $criterion)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single criterion against a transaction.
     */
    protected function matchesCriterion(Transaction $transaction, array $criterion): bool
    {
        $field    = $criterion['field']    ?? null;
        $operator = $criterion['operator'] ?? 'contains';
        $value    = (string) ($criterion['value'] ?? '');

        if (!$field) {
            return true;
        }

        // Resolve field value — support relation fields
        $fieldValue = match ($field) {
            'account_name' => (string) ($transaction->account?->name ?? ''),
            default        => (string) ($transaction->{$field} ?? ''),
        };

        return match ($operator) {
            'contains'    => str_contains(strtolower($fieldValue), strtolower($value)),
            'equals'      => strtolower($fieldValue) === strtolower($value),
            'starts_with' => str_starts_with(strtolower($fieldValue), strtolower($value)),
            'ends_with'   => str_ends_with(strtolower($fieldValue), strtolower($value)),
            'gt'          => (float) $fieldValue > (float) $value,
            'lt'          => (float) $fieldValue < (float) $value,
            default       => false,
        };
    }

    /**
     * Apply a custom rule's action to a transaction.
     */
    protected function applyCustomRuleAction(Transaction $transaction, Rule $rule): void
    {
        $action = $rule->action ?? [];

        if (!empty($action['type'])) {
            $transaction->type = $action['type'];
        }

        if (!empty($action['category_id'])) {
            $transaction->category_id = (int) $action['category_id'];
        }

        $transaction->status          = 'COMPLETED';
        $transaction->applied_rule_id = $rule->id;

        // Auto-link with matching transaction in another account
        if (!empty($action['auto_link_matching'])) {
            $this->tryAutoLink($transaction);
        }

        // Create reverse transaction if configured
        if (!empty($action['reverse_account_id']) && !$transaction->linked_transaction_id) {
            // Always use INCOME/EXPENSE (never TRANSFER) for reversed transactions
            $reverseType = $transaction->type === 'EXPENSE' ? 'INCOME' : 'EXPENSE';
            $reversed = Transaction::create([
                'account_id'            => (int) $action['reverse_account_id'],
                'occurred_at'           => $transaction->occurred_at,
                'amount'                => abs($transaction->amount),
                'amount_eur'            => abs($transaction->amount_eur ?? $transaction->amount),
                'currency'              => $transaction->currency ?? 'EUR',
                'exchange_rate'         => $transaction->exchange_rate ?? 1,
                'type'                  => $reverseType,
                'status'                => 'COMPLETED',
                'description'           => $transaction->description,
                'counterparty_name'     => $transaction->counterparty_name,
                'reference'             => $transaction->reference,
                'applied_rule_id'       => $rule->id,
                'linked_transaction_id' => $transaction->id,
            ]);
            $transaction->linked_transaction_id = $reversed->id;

            Log::info('Reverse transaction created by rule', [
                'original_id'  => $transaction->id,
                'reversed_id'  => $reversed->id,
                'rule_id'      => $rule->id,
                'target_account' => $action['reverse_account_id'],
            ]);
        }

        $transaction->save();

        Log::info('Custom rule applied to transaction', [
            'transaction_id' => $transaction->id,
            'rule_id'        => $rule->id,
            'rule_name'      => $rule->name,
        ]);
    }

    /**
     * Try to find a matching transaction in another account and create a bidirectional link.
     * Matches by: same description + same date (±1 day) + same amount_eur.
     */
    protected function tryAutoLink(Transaction $transaction): bool
    {
        if ($transaction->linked_transaction_id) {
            return false; // already linked
        }

        $amountEur = abs($transaction->amount_eur ?? $transaction->amount);

        $match = Transaction::where('id', '!=', $transaction->id)
            ->where('account_id', '!=', $transaction->account_id)
            ->whereNull('linked_transaction_id')
            ->where('description', $transaction->description)
            ->whereBetween('occurred_at', [
                $transaction->occurred_at->copy()->subDay(),
                $transaction->occurred_at->copy()->addDay(),
            ])
            ->whereRaw('ABS(COALESCE(amount_eur, amount) - ?) < 0.01', [$amountEur])
            ->first();

        if ($match) {
            $transaction->linked_transaction_id = $match->id;
            $match->update(['linked_transaction_id' => $transaction->id]);

            Log::info('Auto-link created', [
                'transaction_id' => $transaction->id,
                'linked_to'      => $match->id,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Create or update the built-in system rules (bank fees, cash, auto-link).
     * Safe to call multiple times — uses firstOrCreate by name.
     */
    public function createDefaultRules(): void
    {
        // --- Rule 1: Bankas komisijas ---
        $feeCategory = Category::firstOrCreate(
            ['name' => 'Bankas komisijas'],
            ['type' => 'EXPENSE']
        );
        Rule::firstOrCreate(
            ['name' => '⚙ Bankas komisijas'],
            [
                'priority'  => 100,
                'is_active' => true,
                'criteria'  => [
                    'and_criteria' => [],
                    'or_criteria'  => [
                        ['field' => 'description', 'operator' => 'contains', 'value' => 'komisija'],
                        ['field' => 'description', 'operator' => 'contains', 'value' => 'commission'],
                        ['field' => 'description', 'operator' => 'contains', 'value' => 'apkalpošanas maksa'],
                        ['field' => 'description', 'operator' => 'contains', 'value' => 'service fee'],
                        ['field' => 'description', 'operator' => 'contains', 'value' => 'bankas pakalpojumi'],
                    ],
                ],
                'action'    => [
                    'type'        => 'EXPENSE',
                    'category_id' => $feeCategory->id,
                ],
            ]
        );

        // --- Rule 2: Skaidra nauda / ATM ---
        $cashCategory = Category::firstOrCreate(
            ['name' => 'Skaidra nauda'],
            ['type' => 'EXPENSE']
        );
        Rule::firstOrCreate(
            ['name' => '⚙ Skaidra nauda / ATM'],
            [
                'priority'  => 90,
                'is_active' => true,
                'criteria'  => [
                    'and_criteria' => [],
                    'or_criteria'  => [
                        ['field' => 'description',      'operator' => 'contains', 'value' => 'bankomāt'],
                        ['field' => 'description',      'operator' => 'contains', 'value' => 'atm'],
                        ['field' => 'description',      'operator' => 'contains', 'value' => 'skaidra nauda'],
                        ['field' => 'description',      'operator' => 'contains', 'value' => 'cash'],
                        ['field' => 'counterparty_name','operator' => 'contains', 'value' => 'kase'],
                    ],
                ],
                'action'    => [
                    'category_id' => $cashCategory->id,
                ],
            ]
        );

        // --- Rule 3: Auto-sasaiste starp kontiem ---
        Rule::firstOrCreate(
            ['name' => '⚙ Auto-sasaiste starp kontiem'],
            [
                'priority'  => 50,
                'is_active' => false, // user enables manually after review
                'criteria'  => [
                    'and_criteria' => [],
                    'or_criteria'  => [],
                ],
                'action'    => [
                    'auto_link_matching' => true,
                ],
            ]
        );
    }

    /**
     * Process multiple transactions in batch.
     */
    public function processBatch(array $transactionIds): array
    {
        $stats = [
            'processed' => 0,
            'approved' => 0,
            'rules' => [
                'inter_account_transfer' => 0,
                'cash_transaction' => 0,
                'similar_transaction' => 0,
            ],
        ];

        foreach ($transactionIds as $id) {
            $transaction = Transaction::find($id);
            if (!$transaction) {
                continue;
            }

            $stats['processed']++;
            
            if ($this->processTransaction($transaction)) {
                $stats['approved']++;
            }
        }

        return $stats;
    }

    /**
     * Check if transaction is a bank fee.
     */
    protected function isBankFee(Transaction $transaction): bool
    {
        // 1. Check raw payload for bank code
        if (isset($transaction->raw_payload['Bankas_kods']) && $transaction->raw_payload['Bankas_kods'] === 'KOM') {
            return true;
        }

        // 2. Check description for keywords
        $description = strtolower($transaction->description ?? '');
        $feeKeywords = ['komisija', 'commission', 'apkalpošanas maksa', 'service fee', 'bankas pakalpojumi', 'komisija par'];
        
        foreach ($feeKeywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
