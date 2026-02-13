<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Account;
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
}
