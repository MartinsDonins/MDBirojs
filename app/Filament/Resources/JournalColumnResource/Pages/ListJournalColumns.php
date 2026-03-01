<?php

namespace App\Filament\Resources\JournalColumnResource\Pages;

use App\Filament\Resources\JournalColumnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJournalColumns extends ListRecords
{
    protected static string $resource = JournalColumnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Pievienot')
                ->slideOver()
                ->mutateFormDataUsing(function (array $data): array {
                    if (isset($data['vid_columns_text'])) {
                        $data['vid_columns'] = array_values(array_filter(
                            array_map('intval', array_map('trim', explode(',', $data['vid_columns_text'])))
                        ));
                        unset($data['vid_columns_text']);
                    }
                    return $data;
                }),
        ];
    }
}
