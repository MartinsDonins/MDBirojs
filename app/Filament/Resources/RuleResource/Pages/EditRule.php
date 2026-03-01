<?php

namespace App\Filament\Resources\RuleResource\Pages;

use App\Filament\Resources\RuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRule extends EditRecord
{
    protected static string $resource = RuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Transform criteria JSON → separate and_criteria / or_criteria fields for the form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $criteria = $data['criteria'] ?? [];

        if (isset($criteria['and_criteria']) || isset($criteria['or_criteria'])) {
            // Already in new AND/OR format
            $data['and_criteria'] = $criteria['and_criteria'] ?? [];
            $data['or_criteria']  = $criteria['or_criteria']  ?? [];
        } else {
            // Old flat array — treat all as AND criteria
            $data['and_criteria'] = is_array($criteria) ? array_values($criteria) : [];
            $data['or_criteria']  = [];
        }

        unset($data['criteria']);
        return $data;
    }

    /**
     * Assemble and_criteria + or_criteria back into the criteria JSON field before saving.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['criteria'] = [
            'and_criteria' => array_values($data['and_criteria'] ?? []),
            'or_criteria'  => array_values($data['or_criteria']  ?? []),
        ];

        unset($data['and_criteria'], $data['or_criteria']);
        return $data;
    }
}
