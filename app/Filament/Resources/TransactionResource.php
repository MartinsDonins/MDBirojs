<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $modelLabel = 'Darījums';

    protected static ?string $pluralModelLabel = 'Darījumi';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pamatdati')
                            ->schema([
                                Forms\Components\Select::make('account_id')
                                    ->label('Konts')
                                    ->relationship('account', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('category_id')
                                    ->label('Kategorija')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->label('Nosaukums')->required(),
                                        Forms\Components\Select::make('type')
                                            ->label('Tips')
                                            ->options(['INCOME' => 'Ieņēmumi', 'EXPENSE' => 'Izdevumi']),
                                    ]),
                                Forms\Components\DatePicker::make('occurred_at')
                                    ->required()
                                    ->maxDate(now()),
                                Forms\Components\DatePicker::make('booked_at'),
                            ])->columns(2),

                        Forms\Components\Section::make('Finanses')
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Summa')
                                    ->required()
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (strtoupper($get('currency') ?? 'EUR') === 'EUR') {
                                            $set('amount_eur', $state);
                                        } else {
                                            $set('amount_eur', round((float) $state * (float) ($get('exchange_rate') ?: 1), 2));
                                        }
                                    }),

                                Forms\Components\Select::make('currency')
                                    ->label('Valūta')
                                    ->options(['EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP', 'SEK' => 'SEK', 'NOK' => 'NOK'])
                                    ->default('EUR')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (strtoupper($state ?? 'EUR') === 'EUR') {
                                            $set('exchange_rate', 1);
                                            $set('amount_eur', $get('amount'));
                                        }
                                    }),

                                Forms\Components\TextInput::make('exchange_rate')
                                    ->label('Maiņas kurss')
                                    ->numeric()
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->hidden(fn (Get $get) => strtoupper($get('currency') ?? 'EUR') === 'EUR')
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $set('amount_eur', round((float) ($get('amount') ?: 0) * (float) ($state ?: 1), 2));
                                    }),

                                Forms\Components\TextInput::make('amount_eur')
                                    ->label('Summa EUR')
                                    ->numeric()
                                    ->disabled(fn (Get $get) => strtoupper($get('currency') ?? 'EUR') === 'EUR')
                                    ->dehydrated()
                                    ->helperText(fn (Get $get) => strtoupper($get('currency') ?? 'EUR') === 'EUR' ? 'Automātiski = Summa' : 'Aprēķināts: Summa × Kurss')
                                    ->hidden(fn (Get $get) => strtoupper($get('currency') ?? 'EUR') === 'EUR'),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Darījuma partneris')
                            ->schema([
                                Forms\Components\TextInput::make('counterparty_name')->label('Nosaukums'),
                                Forms\Components\TextInput::make('counterparty_account')->label('Konta nr.'),
                                Forms\Components\TextInput::make('reference')->label('Atsauce'),
                            ]),

                        Forms\Components\Section::make('Papildinformācija')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Darbības tips')
                                    ->options([
                                        'INCOME' => 'Ieņēmumi',
                                        'EXPENSE' => 'Izdevumi',
                                        'TRANSFER' => 'Pārskaitījums',
                                        'FEE' => 'Komisija',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('status')
                                    ->label('Statuss')
                                    ->options([
                                        'DRAFT' => 'Melnraksts',
                                        'COMPLETED' => 'Pabeigts',
                                        'NEEDS_REVIEW' => 'Jāpārskata',
                                    ])
                                    ->default('COMPLETED')
                                    ->required(),
                                Forms\Components\TextInput::make('fingerprint')
                                    ->label('Nospiedums')
                                    ->disabled()
                                    ->dehydrated(false) // Don't save if disabled and calculated elsewhere
                                    ->visibleOn('edit'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
                    
                Forms\Components\Textarea::make('description')
                    ->label('Apraksts')
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->date()
                    ->sortable()
                    ->label('Datums'),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Konts')
                    ->sortable(),
                Tables\Columns\TextColumn::make('counterparty_name')
                    ->label('Darījuma partneris')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(fn ($state) => $state),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->money(fn ($record) => $record->currency)
                    ->sortable()
                    ->color(fn ($record) => $record->amount > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\SelectColumn::make('type')
                    ->label('Tips')
                    ->options([
                        'INCOME' => 'Ieņēmumi',
                        'EXPENSE' => 'Izdevumi',
                        'TRANSFER' => 'Pārskaitījums',
                        'FEE' => 'Komisija',
                    ])
                    ->selectablePlaceholder(false)
                    ->sortable(),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Statuss')
                    ->options([
                        'DRAFT' => 'Melnraksts',
                        'COMPLETED' => 'Pabeigts',
                        'NEEDS_REVIEW' => 'Jāpārskata',
                    ])
                    ->selectablePlaceholder(false)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account')
                    ->label('Konts')
                    ->relationship('account', 'name'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tips')
                    ->options([
                        'INCOME' => 'Ieņēmumi',
                        'EXPENSE' => 'Izdevumi',
                        'TRANSFER' => 'Pārskaitījums',
                        'FEE' => 'Komisija',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statuss')
                    ->options([
                        'DRAFT' => 'Melnraksts',
                        'COMPLETED' => 'Pabeigts',
                        'NEEDS_REVIEW' => 'Jāpārskata',
                    ]),
                Tables\Filters\SelectFilter::make('applied_rule_id')
                    ->label('Piemērotā kārtula')
                    ->relationship('appliedRule', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Visas kārtulas'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->recordAction('view')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('run_rules')
                        ->label('Piemērot kārtulas')
                        ->icon('heroicon-o-play')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, \App\Services\AutoApprovalService $autoApprovalService) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($autoApprovalService->processTransaction($record)) {
                                    $count++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("Kārtulas piemērotas {$count} darījumiem")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('generate_cash_orders')
                        ->label('Ģenerēt kases orderus')
                        ->icon('heroicon-o-banknotes')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, \App\Services\CashOrderService $cashOrderService) {
                            $cashOrders = $cashOrderService->generateBatch($records->pluck('id')->toArray());
                            
                            \Filament\Notifications\Notification::make()
                                ->title("Izveidoti {count} kases orderi", ['count' => count($cashOrders)])
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
