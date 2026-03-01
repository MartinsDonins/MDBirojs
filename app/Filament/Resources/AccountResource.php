<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Resources\AccountResource\RelationManagers;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $modelLabel = 'Konts';

    protected static ?string $pluralModelLabel = 'Konti';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nosaukums')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->label('Tips')
                    ->options([
                        'BANK' => 'Bankas konts',
                        'PAYPAL' => 'PayPal',
                        'CASH' => 'Skaidra nauda',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->label('Valūta')
                    ->default('EUR')
                    ->required()
                    ->maxLength(3),
                Forms\Components\TextInput::make('account_number')
                    ->label('IBAN / E-pasts')
                    ->maxLength(255),
                Forms\Components\TextInput::make('bank_name')
                    ->label('Bankas nosaukums')
                    ->maxLength(255),
                Forms\Components\TextInput::make('balance')
                    ->label('Atlikums')
                    ->numeric()
                    ->default(0)
                    ->prefix('€'),
                Forms\Components\Select::make('status')
                    ->label('Statuss')
                    ->options([
                        'ACTIVE' => 'Aktīvs',
                        'CLOSED' => 'Slēgts',
                    ])
                    ->default('ACTIVE')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nosaukums')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tips')
                    ->badge()
                    ->colors([
                        'primary' => 'BANK',
                        'info' => 'PAYPAL',
                        'success' => 'CASH',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'BANK' => 'Bankas konts',
                        'PAYPAL' => 'PayPal',
                        'CASH' => 'Skaidra nauda',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('account_number')
                    ->label('IBAN / E-pasts')
                    ->searchable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Atlikums')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statuss')
                    ->badge()
                    ->colors([
                        'success' => 'ACTIVE',
                        'danger' => 'CLOSED',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'ACTIVE' => 'Aktīvs',
                        'CLOSED' => 'Slēgts',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Izveidots')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
