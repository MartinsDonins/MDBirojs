<?php

namespace App\Filament\Resources\RuleResource\Pages;

use App\Filament\Pages\InterAccountSettings;
use App\Filament\Resources\RuleResource;
use App\Models\Rule;
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

            Actions\Action::make('inter_account_settings')
                ->label('Starp kontiem')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->url(InterAccountSettings::getUrl()),

            Actions\Action::make('run_all')
                ->label('Izpildīt visas')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Izpildīt visas aktīvās kārtulas')
                ->modalDescription('Visas aktīvās kārtulas tiks piemērotas visiem DRAFT un NEEDS_REVIEW darījumiem pēc prioritātes kārtībā (augstākā prioritāte vispirms).')
                ->action(function () {
                    $service = app(AutoApprovalService::class);
                    $rules   = Rule::where('is_active', true)->orderByDesc('priority')->get();
                    $totalApplied    = 0;
                    $totalProcessed  = 0;
                    foreach ($rules as $rule) {
                        $stats = $service->applyCustomRule($rule);
                        $totalApplied   += $stats['applied'];
                        $totalProcessed += $stats['processed'];
                    }
                    Notification::make()
                        ->title('Visas aktīvās kārtulas izpildītas')
                        ->body("Pārskatīti: {$totalProcessed} darījumi, piemērotas kārtulas: {$totalApplied}")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('seed_defaults')
                ->label('Pievienot sistēmas kārtulas')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Pievienot sistēmas kārtulas')
                ->modalDescription('Tiks izveidotas gatavās kārtulas: Bankas komisijas, Auto-sasaiste starp kontiem. Ja šāda kārtula jau eksistē — netiks izveidota atkārtoti.')
                ->action(function () {
                    app(AutoApprovalService::class)->createDefaultRules();
                    Notification::make()
                        ->title('Sistēmas kārtulas pievienotas')
                        ->body('Izveidotas: ⚙ Bankas komisijas, ⚙ Auto-sasaiste starp kontiem')
                        ->success()
                        ->send();
                }),
        ];
    }
}
