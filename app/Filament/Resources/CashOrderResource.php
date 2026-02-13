<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashOrderResource\Pages;
use App\Models\CashOrder;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashOrderResource extends Resource
{
    protected static ?string $model = CashOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationLabel = 'Kases Orderi';
    
    protected static ?string $modelLabel = 'Kases Orderis';
    
    protected static ?string $pluralModelLabel = 'Kases Orderi';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pamatinformācija')
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->label('Kases Konts')
                            ->relationship('account', 'name', fn (Builder $query) => 
                                $query->where('type', 'CASH')
                            )
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('type')
                            ->label('Tips')
                            ->options([
                                'INCOME' => 'Ienākums (KII)',
                                'EXPENSE' => 'Izdevums (KIO)',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state && !$get('number')) {
                                    $set('number', CashOrder::generateNumber($state));
                                }
                            }),

                        Forms\Components\TextInput::make('number')
                            ->label('Numurs')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(fn (Forms\Get $get) => 
                                $get('type') ? CashOrder::generateNumber($get('type')) : null
                            ),

                        Forms\Components\DatePicker::make('date')
                            ->label('Datums')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('amount')
                            ->label('Summa')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->minValue(0.01)
                            ->step(0.01),

                        Forms\Components\TextInput::make('currency')
                            ->label('Valūta')
                            ->default('EUR')
                            ->required()
                            ->maxLength(3),
                    ])->columns(2),

                Forms\Components\Section::make('Detaļas')
                    ->schema([
                        Forms\Components\TextInput::make('person')
                            ->label('Persona')
                            ->helperText('Kam (ienākums) vai No kā (izdevums)')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('basis')
                            ->label('Pamatojums')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Piezīmes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Saistītā Transakcija')
                    ->schema([
                        Forms\Components\Select::make('transaction_id')
                            ->label('Transakcija')
                            ->relationship('transaction', 'id')
                            ->searchable()
                            ->preload()
                            ->helperText('Izvēlies, ja šis orderis ir saistīts ar konkrētu transakciju'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Numurs')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tips')
                    ->colors([
                        'success' => 'INCOME',
                        'danger' => 'EXPENSE',
                    ])
                    ->formatStateUsing(fn (string $state): string => 
                        $state === 'INCOME' ? 'KII' : 'KIO'
                    ),

                Tables\Columns\TextColumn::make('date')
                    ->label('Datums')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('person')
                    ->label('Persona')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('basis')
                    ->label('Pamatojums')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Konts')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Izveidots')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tips')
                    ->options([
                        'INCOME' => 'Ienākums (KII)',
                        'EXPENSE' => 'Izdevums (KIO)',
                    ]),

                Tables\Filters\SelectFilter::make('account_id')
                    ->label('Konts')
                    ->relationship('account', 'name'),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('No'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Līdz'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
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
            'index' => Pages\ListCashOrders::route('/'),
            'create' => Pages\CreateCashOrder::route('/create'),
            'edit' => Pages\EditCashOrder::route('/{record}/edit'),
        ];
    }
}
