<?php

namespace App\Observers;

use App\Models\CashOrder;
use App\Models\Transaction;

class TransactionObserver
{
    /**
     * Auto-create a cash order whenever a CASH-account transaction reaches COMPLETED status.
     *
     * Fires on both create and update (via the `saved` hook), so it handles:
     *  - Transactions imported and auto-approved in the same request
     *  - Transactions manually set to COMPLETED through the UI
     *  - Transactions that are created with status=COMPLETED directly
     */
    public function saved(Transaction $transaction): void
    {
        if ($transaction->status !== 'COMPLETED') {
            return;
        }

        // Load the account if it isn't already loaded
        $account = $transaction->relationLoaded('account')
            ? $transaction->account
            : $transaction->account()->first();

        if (!$account || $account->type !== 'CASH') {
            return;
        }

        // Prevent duplicate cash orders for the same transaction
        if (CashOrder::where('transaction_id', $transaction->id)->exists()) {
            return;
        }

        $cashType = ((float) $transaction->amount >= 0) ? 'INCOME' : 'EXPENSE';
        $year     = ($transaction->occurred_at ?? now())->year;

        CashOrder::create([
            'transaction_id' => $transaction->id,
            'account_id'     => $transaction->account_id,
            'type'           => $cashType,
            'number'         => CashOrder::generateNumber($cashType, $year),
            'date'           => $transaction->occurred_at ?? now(),
            'amount'         => abs((float) ($transaction->amount_eur ?? $transaction->amount)),
            'currency'       => $transaction->currency ?? 'EUR',
            'basis'          => $transaction->description,
            'person'         => $transaction->counterparty_name,
        ]);
    }
}
