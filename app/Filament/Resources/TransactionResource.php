<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Core Details')
                            ->schema([
                                Forms\Components\Select::make('account_id')
                                    ->relationship('account', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\Select::make('type')
                                            ->options(['INCOME' => 'Income', 'EXPENSE' => 'Expense']),
                                    ]),
                                Forms\Components\DatePicker::make('occurred_at')
                                    ->required()
                                    ->maxDate(now()),
                                Forms\Components\DatePicker::make('booked_at'),
                            ])->columns(2),

                        Forms\Components\Section::make('Financials')
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->required()
                                    ->numeric(),
                                Forms\Components\TextInput::make('currency')
                                    ->default('EUR')
                                    ->required()
                                    ->maxLength(3),
                                Forms\Components\TextInput::make('amount_eur')
                                    ->label('Amount (EUR)')
                                    ->required() // In real app, this should be auto-calculated
                                    ->numeric(),
                                Forms\Components\TextInput::make('exchange_rate')
                                    ->numeric()
                                    ->default(1),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Counterparty')
                            ->schema([
                                Forms\Components\TextInput::make('counterparty_name'),
                                Forms\Components\TextInput::make('counterparty_account'),
                                Forms\Components\TextInput::make('reference'),
                            ]),

                        Forms\Components\Section::make('Meta')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'INCOME' => 'Income',
                                        'EXPENSE' => 'Expense',
                                        'TRANSFER' => 'Transfer',
                                        'FEE' => 'Fee',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'DRAFT' => 'Draft',
                                        'COMPLETED' => 'Completed',
                                        'NEEDS_REVIEW' => 'Needs Review',
                                    ])
                                    ->default('COMPLETED')
                                    ->required(),
                                Forms\Components\TextInput::make('fingerprint')
                                    ->disabled()
                                    ->dehydrated(false) // Don't save if disabled and calculated elsewhere
                                    ->visibleOn('edit'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
                    
                Forms\Components\Textarea::make('description')
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
                    ->label('Date'),
                Tables\Columns\TextColumn::make('account.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('counterparty_name')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(fn ($state) => $state),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable()
                    ->color(fn ($record) => $record->amount > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('category.name')
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
                        ->label('Run Rules')
                        ->icon('heroicon-o-play')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, \App\Services\AutoApprovalService $autoApprovalService) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($autoApprovalService->processTransaction($record)) {
                                    $count++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("Rules applied to {$count} transactions")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('generate_cash_orders')
                        ->label('Generate Cash Orders')
                        ->icon('heroicon-o-banknotes')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, \App\Services\CashOrderService $cashOrderService) {
                            $cashOrders = $cashOrderService->generateBatch($records->pluck('id')->toArray());
                            
                            \Filament\Notifications\Notification::make()
                                ->title("Generated {count} cash orders", ['count' => count($cashOrders)])
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
