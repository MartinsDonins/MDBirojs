<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $currency = strtoupper($data['currency'] ?? 'EUR');
        if ($currency === 'EUR') {
            $data['exchange_rate'] = 1;
            $data['amount_eur']    = $data['amount'];
        } else {
            $rate                  = (float) ($data['exchange_rate'] ?? 1) ?: 1;
            $data['amount_eur']    = round((float) $data['amount'] * $rate, 2);
        }
        return $data;
    }
}
