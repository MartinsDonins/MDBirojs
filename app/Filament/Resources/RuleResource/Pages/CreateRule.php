<?php

namespace App\Filament\Resources\RuleResource\Pages;

use App\Filament\Resources\RuleResource;
use App\Services\AutoApprovalService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRule extends CreateRecord
{
    protected static string $resource = RuleResource::class;

    /** Set to true when "Saglabāt un izpildīt" button is used. */
    public bool $runAfterCreate = false;

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

    /**
     * After the record is created, optionally run the rule immediately.
     */
    protected function afterCreate(): void
    {
        if (! $this->runAfterCreate) {
            return;
        }

        $stats = app(AutoApprovalService::class)->applyCustomRule($this->record);

        Notification::make()
            ->title('Kārtula izpildīta')
            ->body("Pārskatīti: {$stats['processed']} darījumi, piemēroti: {$stats['applied']}")
            ->success()
            ->persistent()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),

            Action::make('createAndRun')
                ->label('Saglabāt un izpildīt')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action(function () {
                    $this->runAfterCreate = true;
                    $this->create();
                }),

            $this->getCancelFormAction(),
        ];
    }
}
