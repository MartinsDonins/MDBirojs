<?php

namespace App\Filament\Resources\CashOrderResource\Pages;

use App\Filament\Resources\CashOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashOrders extends ListRecords
{
    protected static string $resource = CashOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
