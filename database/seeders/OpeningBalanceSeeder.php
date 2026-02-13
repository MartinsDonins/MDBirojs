<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;

class OpeningBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $date = Carbon::parse('2014-01-01 00:01:00');

        $balances = [
            // Kase (EUR)
            [
                'account_query' => ['name' => 'Kasē (EUR)'],
                'amount' => 706.38,
            ],
            // Kredītiestāžu konti 2 (EUR) LV48HABA0551024324813 -> 5.00 EUR (updated)
            [
                'account_query' => ['account_number' => 'LV48HABA0551024324813'],
                'amount' => 5.00,
            ],
            // Kredītiestāžu konti 1 (EUR) LV62HABA0551038171311 -> 0.00 EUR (Skipping)
        ];

        foreach ($balances as $data) {
            $account = Account::where($data['account_query'])->first();

            if ($account) {
                // Check if opening balance already exists to prevent duplication
                $exists = Transaction::where('account_id', $account->id)
                    ->where('description', 'Sākuma atlikums')
                    ->where('occurred_at', $date)
                    ->exists();

                if (!$exists) {
                    Transaction::create([
                        'account_id' => $account->id,
                        'amount' => $data['amount'],
                        'amount_eur' => $data['amount'], // Assuming EUR
                        'currency' => 'EUR',
                        'occurred_at' => $date,
                        'description' => 'Sākuma atlikums',
                        'type' => 'INCOME', // Treat as income to increase balance
                        'status' => 'COMPLETED',
                        'fingerprint' => 'OPENING_BALANCE_' . $account->id . '_' . $date->timestamp,
                    ]);
                    $this->command->info("Created opening balance for {$account->name}: {$data['amount']} EUR");
                } else {
                    $this->command->warn("Opening balance already exists for {$account->name}");
                }
            } else {
                $this->command->error("Account not found for query: " . json_encode($data['account_query']));
            }
        }
    }
}
