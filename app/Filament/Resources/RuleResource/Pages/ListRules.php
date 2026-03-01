<?php

namespace App\Filament\Resources\RuleResource\Pages;

use App\Filament\Resources\RuleResource;
use App\Services\AutoApprovalService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRules extends ListRecords
{
    protected static string $resource = RuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Pievienot'),

            Actions\Action::make('seed_defaults')
                ->label('Pievienot sistēmas kārtulas')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Pievienot sistēmas kārtulas')
                ->modalDescription('Tiks izveidotas 3 gatavās kārtulas: Bankas komisijas, Skaidra nauda/ATM un Auto-sasaiste starp kontiem. Ja šāda kārtula jau eksistē — netiks izveidota atkārtoti.')
                ->action(function () {
                    app(AutoApprovalService::class)->createDefaultRules();
                    Notification::make()
                        ->title('Sistēmas kārtulas pievienotas')
                        ->body('Izveidotas: ⚙ Bankas komisijas, ⚙ Skaidra nauda / ATM, ⚙ Auto-sasaiste starp kontiem')
                        ->success()
                        ->send();
                }),
        ];
    }
}
