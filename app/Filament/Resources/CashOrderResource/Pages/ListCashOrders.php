<?php

namespace App\Filament\Resources\CashOrderResource\Pages;

use App\Filament\Resources\CashOrderResource;
use App\Models\Account;
use App\Models\CashOrder;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListCashOrders extends ListRecords
{
    protected static string $resource = CashOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Pievienot'),

            Actions\Action::make('generate_missing')
                ->label('Izveidot trūkstošos orderus')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Izveidot trūkstošos kases orderus')
                ->modalDescription(
                    'Tiks atrasti visi PABEIGTI darījumi kases kontos, kuriem vēl nav izveidots kases orderis, '
                    . 'un tiem tiks automātiski izveidoti KII / KIO orderi.'
                )
                ->action(function (): void {
                    // Cash + PayPal + Paysera account IDs
                    $cashAccountIds = Account::whereIn('type', ['CASH', 'PAYPAL', 'PAYSERA'])->pluck('id');

                    if ($cashAccountIds->isEmpty()) {
                        Notification::make()
                            ->title('Nav kases / PayPal / Paysera kontu')
                            ->warning()
                            ->send();
                        return;
                    }

                    // COMPLETED transactions for CASH/PAYPAL/PAYSERA accounts without a cash order
                    $transactions = Transaction::whereIn('account_id', $cashAccountIds)
                        ->where('status', 'COMPLETED')
                        ->whereDoesntHave('cashOrder')
                        ->with('account')
                        ->orderBy('occurred_at')
                        ->get();

                    if ($transactions->isEmpty()) {
                        Notification::make()
                            ->title('Nav trūkstošu orderu')
                            ->body('Visi kases darījumi jau ir ar orderiem.')
                            ->success()
                            ->send();
                        return;
                    }

                    $created = 0;

                    DB::transaction(function () use ($transactions, &$created): void {
                        foreach ($transactions as $tx) {
                            $cashType = ((float) $tx->amount >= 0) ? 'INCOME' : 'EXPENSE';
                            $year     = ($tx->occurred_at ?? now())->year;

                            CashOrder::create([
                                'transaction_id' => $tx->id,
                                'type'           => $cashType,
                                'number'         => CashOrder::generateNumber($cashType, $year),
                                'date'           => $tx->occurred_at ?? now(),
                                'amount'         => abs((float) ($tx->amount_eur ?? $tx->amount)),
                                'currency'       => $tx->currency ?? 'EUR',
                                'basis'          => $tx->description,
                                'person'         => $tx->counterparty_name,
                            ]);

                            $created++;
                        }
                    });

                    Notification::make()
                        ->title('Kases orderi izveidoti')
                        ->body("Izveidoti: {$created} orderi ({$transactions->count()} darījumi).")
                        ->success()
                        ->send();
                }),
        ];
    }
}
