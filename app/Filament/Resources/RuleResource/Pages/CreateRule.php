<?php

namespace App\Filament\Resources\RuleResource\Pages;

use App\Filament\Resources\RuleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRule extends CreateRecord
{
    protected static string $resource = RuleResource::class;

    /**
     * Assemble and_criteria + or_criteria into the criteria JSON field before creating.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criteria'] = [
            'and_criteria' => array_values($data['and_criteria'] ?? []),
            'or_criteria'  => array_values($data['or_criteria']  ?? []),
        ];

        unset($data['and_criteria'], $data['or_criteria']);
        return $data;
    }
}
