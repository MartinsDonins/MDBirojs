<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Darījumi šajā kategorijā';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Datums')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Konts')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Apraksts')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('counterparty_name')
                    ->label('Darījuma partneris')
                    ->limit(30)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: ' ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tips')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'INCOME'   => 'success',
                        'EXPENSE'  => 'danger',
                        'FEE'      => 'warning',
                        'TRANSFER' => 'info',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'INCOME'   => 'Ieņēmumi',
                        'EXPENSE'  => 'Izdevumi',
                        'FEE'      => 'Komisija',
                        'TRANSFER' => 'Pārskaitīj.',
                        default    => $state,
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statuss')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'COMPLETED'    => 'success',
                        'DRAFT'        => 'gray',
                        'NEEDS_REVIEW' => 'warning',
                        default        => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'COMPLETED'    => 'Apstiprināts',
                        'DRAFT'        => 'Melnraksts',
                        'NEEDS_REVIEW' => 'Pārskatīt',
                        default        => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tips')
                    ->options([
                        'INCOME'   => 'Ieņēmumi',
                        'EXPENSE'  => 'Izdevumi',
                        'FEE'      => 'Komisija',
                        'TRANSFER' => 'Pārskaitījums',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statuss')
                    ->options([
                        'COMPLETED'    => 'Apstiprināts',
                        'DRAFT'        => 'Melnraksts',
                        'NEEDS_REVIEW' => 'Pārskatīt',
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('remove_category')
                    ->label('Noņemt kategoriju')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Noņemt kategoriju no darījuma')
                    ->modalDescription('Darījums paliks bez kategorijas. Darījuma statuss netiks mainīts.')
                    ->action(function ($record) {
                        $record->category_id = null;
                        $record->save();

                        Notification::make()
                            ->title('Kategorija noņemta')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('remove_category_bulk')
                    ->label('Noņemt kategoriju atzīmētajiem')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Noņemt kategoriju atzīmētajiem darījumiem')
                    ->modalDescription('Visiem atzīmētajiem darījumiem tiks noņemta kategorija. Darījumu statuss netiks mainīts.')
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            $record->category_id = null;
                            $record->save();
                            $count++;
                        }

                        Notification::make()
                            ->title("Kategorija noņemta {$count} darījumiem")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
