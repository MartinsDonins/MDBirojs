<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
