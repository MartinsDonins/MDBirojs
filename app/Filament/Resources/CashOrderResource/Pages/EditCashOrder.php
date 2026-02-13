<?php

namespace App\Filament\Resources\CashOrderResource\Pages;

use App\Filament\Resources\CashOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCashOrder extends EditRecord
{
    protected static string $resource = CashOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
