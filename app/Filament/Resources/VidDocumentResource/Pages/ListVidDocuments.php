<?php

namespace App\Filament\Resources\VidDocumentResource\Pages;

use App\Filament\Resources\VidDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVidDocuments extends ListRecords
{
    protected static string $resource = VidDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Pievienot')
                ->slideOver(),
        ];
    }
}
