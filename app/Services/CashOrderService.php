<?php

namespace App\Services;

use App\Models\CashOrder;
use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class CashOrderService
{
    /**
     * Generate a cash order from a transaction
     */
    public function generateFromTransaction(Transaction $transaction): CashOrder
    {
        // Verify transaction is cash-related
        if (!$this->isCashTransaction($transaction)) {
            throw new \InvalidArgumentException('Transaction is not a cash transaction');
        }

        // Check if cash order already exists
        if ($transaction->cashOrder()->exists()) {
            throw new \RuntimeException('Cash order already exists for this transaction');
        }

        return DB::transaction(function () use ($transaction) {
            $type = $transaction->amount > 0 ? 'INCOME' : 'EXPENSE';
            
            $cashOrder = CashOrder::create([
                'transaction_id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'number' => CashOrder::generateNumber($type),
                'type' => $type,
                'amount' => abs($transaction->amount),
                'currency' => $transaction->currency,
                'date' => $transaction->occurred_at,
                'basis' => $this->generateBasis($transaction),
                'person' => $transaction->counterparty_name,
                'notes' => $transaction->description,
            ]);

            return $cashOrder;
        });
    }

    /**
     * Generate cash orders for multiple transactions
     */
    public function generateBatch(array $transactionIds): array
    {
        $transactions = Transaction::whereIn('id', $transactionIds)
            ->whereDoesntHave('cashOrder')
            ->get();

        $cashOrders = [];
        
        foreach ($transactions as $transaction) {
            if ($this->isCashTransaction($transaction)) {
                try {
                    $cashOrders[] = $this->generateFromTransaction($transaction);
                } catch (\Exception $e) {
                    // Log error but continue with other transactions
                    \Log::error('Failed to generate cash order', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $cashOrders;
    }

    /**
     * Check if transaction is cash-related
     */
    protected function isCashTransaction(Transaction $transaction): bool
    {
        // Check if account is CASH type
        return $transaction->account && $transaction->account->type === 'CASH';
    }

    /**
     * Generate basis text for cash order
     */
    protected function generateBasis(Transaction $transaction): string
    {
        $parts = [];

        if ($transaction->category) {
            $parts[] = $transaction->category->name;
        }

        if ($transaction->description) {
            $parts[] = $transaction->description;
        }

        if ($transaction->reference) {
            $parts[] = "Ref: {$transaction->reference}";
        }

        return implode(' | ', $parts) ?: 'Kases operācija';
    }

    /**
     * Validate cash balance after transaction
     */
    public function validateCashBalance(Account $account, \Carbon\Carbon $date = null): array
    {
        $date = $date ?? now();
        
        $balance = CashOrder::where('account_id', $account->id)
            ->where('date', '<=', $date)
            ->get()
            ->reduce(function ($carry, $order) {
                return $carry + ($order->type === 'INCOME' ? $order->amount : -$order->amount);
            }, 0);

        return [
            'balance' => $balance,
            'is_negative' => $balance < 0,
            'date' => $date,
        ];
    }

    /**
     * Get cash balance history for an account
     */
    public function getCashBalanceHistory(Account $account, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $orders = CashOrder::where('account_id', $account->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $history = [];
        $runningBalance = 0;

        foreach ($orders as $order) {
            $runningBalance += $order->type === 'INCOME' ? $order->amount : -$order->amount;
            
            $history[] = [
                'date' => $order->date,
                'number' => $order->number,
                'type' => $order->type,
                'amount' => $order->amount,
                'balance' => $runningBalance,
                'person' => $order->person,
                'basis' => $order->basis,
            ];
        }

        return $history;
    }
}
