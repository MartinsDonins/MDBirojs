<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskCategoryResource\Pages;
use App\Models\TaskCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaskCategoryResource extends Resource
{
    protected static ?string $model = TaskCategory::class;

    protected static ?string $modelLabel = 'Kategorija';

    protected static ?string $pluralModelLabel = 'Kategorijas';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Uzdevumi';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nosaukums')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Krāsa')
                            ->default('#6366f1')
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->width('40px'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nosaukums')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tasks_count')
                    ->label('Uzdevumi')
                    ->counts('tasks')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Pievienots')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTaskCategories::route('/'),
            'create' => Pages\CreateTaskCategory::route('/create'),
            'edit'   => Pages\EditTaskCategory::route('/{record}/edit'),
        ];
    }
}
