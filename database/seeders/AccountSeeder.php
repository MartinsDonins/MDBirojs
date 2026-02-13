<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Kasē (EUR)',
                'type' => 'CASH',
                'currency' => 'EUR',
                'account_number' => null,
                'bank_name' => null,
                'balance' => 0,
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Kasē (USD)',
                'type' => 'CASH',
                'currency' => 'USD',
                'account_number' => null,
                'bank_name' => null,
                'balance' => 0,
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Paypal (EUR)',
                'type' => 'PAYPAL',
                'currency' => 'EUR',
                'account_number' => null,
                'bank_name' => 'PayPal',
                'balance' => 0,
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Paypal (USD)',
                'type' => 'PAYPAL',
                'currency' => 'USD',
                'account_number' => null,
                'bank_name' => 'PayPal',
                'balance' => 0,
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'PaySera (EUR)',
                'type' => 'PAYSERA',
                'currency' => 'EUR',
                'account_number' => null,
                'bank_name' => 'PaySera',
                'balance' => 0,
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Darījumu konts',
                'type' => 'BANK',
                'currency' => 'EUR',
                'account_number' => 'LV62HABA0551038171311',
                'bank_name' => 'Swedbank',
                'balance' => 0,
                'status' => 'ACTIVE',
            ],
            [
                'name' => 'Kartes konts',
                'type' => 'BANK',
                'currency' => 'EUR',
                'account_number' => 'LV48HABA0551024324813',
                'bank_name' => 'Swedbank',
                'balance' => 0,
                'status' => 'ACTIVE',
            ],
        ];

        foreach ($accounts as $accountData) {
            Account::firstOrCreate(
                [
                    'account_number' => $accountData['account_number'],
                    'name' => $accountData['name']
                ],
                $accountData
            );
        }
    }
}
