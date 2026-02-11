<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Detect transfers between own accounts.
     * Looks for transactions with same amount (one negative, one positive)
     * within a short time window.
     */
    public function detectTransfers(int $batchId = null): int
    {
        // Setup query for potential outgoing transactions (Expense which might be a transfer)
        $query = Transaction::where('type', 'EXPENSE')
            ->whereNull('category_id') // Only process uncategorized or specifically marked?
            ->where('status', '!=', 'COMPLETED'); // Process DRAFT/PENDING

        if ($batchId) {
            $query->where('import_batch_id', $batchId);
        }

        $candidates = $query->get();
        $matchesFound = 0;

        foreach ($candidates as $outgoing) {
            // Look for incoming transaction with same absolute amount
            // Within +/- 3 days
            $minDate = $outgoing->occurred_at->copy()->subDays(3);
            $maxDate = $outgoing->occurred_at->copy()->addDays(3);
            
            $incoming = Transaction::where('type', 'INCOME')
                ->where('amount', abs($outgoing->amount))
                ->whereBetween('occurred_at', [$minDate, $maxDate])
                ->where('id', '!=', $outgoing->id)
                ->where('account_id', '!=', $outgoing->account_id) // Different account
                ->first();

            if ($incoming) {
                // Determine which is "TRANSFER" type. usually both.
                // We update both to be 'TRANSFER' and link them maybe?
                // For now, let's just mark them as TRANSFER type so they are filtered out of income/expense reports.
                
                DB::transaction(function () use ($outgoing, $incoming) {
                    $outgoing->update([
                        'type' => 'TRANSFER',
                        'description' => $outgoing->description . ' (Transfer to ' . $incoming->account->name . ')',
                        'status' => 'COMPLETED' // Auto-complete transfers? Or keep for review?
                    ]);
                    
                    $incoming->update([
                        'type' => 'TRANSFER',
                        'description' => $incoming->description . ' (Transfer from ' . $outgoing->account->name . ')',
                        'status' => 'COMPLETED'
                    ]);
                });

                $matchesFound++;
            }
        }

        return $matchesFound;
    }
}
