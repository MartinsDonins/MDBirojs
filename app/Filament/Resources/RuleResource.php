<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuleResource\Pages;
use App\Filament\Resources\RuleResource\RelationManagers;
use App\Models\Rule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RuleResource extends Resource
{
    protected static ?string $model = Rule::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                
                Forms\Components\TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->required(),
                
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),

                Forms\Components\Section::make('Criteria')
                    ->schema([
                        Forms\Components\Repeater::make('criteria')
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->options([
                                        'description' => 'Description',
                                        'counterparty_name' => 'Counterparty Name',
                                        'amount' => 'Amount',
                                        'reference' => 'Reference',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('operator')
                                    ->options([
                                        'contains' => 'Contains',
                                        'equals' => 'Equals',
                                        'starts_with' => 'Starts With',
                                        'ends_with' => 'Ends With',
                                        'gt' => 'Greater Than',
                                        'lt' => 'Less Than',
                                    ])
                                    ->default('contains')
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(1),
                    ]),

                Forms\Components\Section::make('Action')
                    ->schema([
                        Forms\Components\Select::make('action.category_id')
                            ->label('Set Category')
                            ->options(\App\Models\Category::pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('action.type')
                            ->label('Set Type')
                            ->options([
                                'INCOME' => 'Income',
                                'EXPENSE' => 'Expense',
                                'TRANSFER' => 'Transfer',
                            ]),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('criteria')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' items' : '0 items')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
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
            ])
            ->defaultSort('priority', 'desc');
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
            'index' => Pages\ListRules::route('/'),
            'create' => Pages\CreateRule::route('/create'),
            'edit' => Pages\EditRule::route('/{record}/edit'),
        ];
    }
}
