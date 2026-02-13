<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'INCOME' => 'Income',
                        'EXPENSE' => 'Expense',
                    ]),
                Forms\Components\Select::make('vid_column')
                    ->label('VID Kolonna')
                    ->options([
                        // Ieņēmumi
                        4 => '4 - Kase (ieņēmumi)',
                        5 => '5 - Maksājumu konts (ieņēmumi)',
                        6 => '6 - Citi maksājuma līdzekļi (ieņēmumi)',
                        7 => '7 - Ieņēmumi (kopā)',
                        8 => '8 - Ieņēmumi, kas nav attiecināmi uz nodokļa aprēķināšanu',
                        9 => '9 - Subsīdijas',
                        10 => '10 - Neapliekamie ieņēmumi',
                        11 => '11 - Ar saimn. darbību tieši nesaistītas izmaksas',
                        // Izdevumi
                        12 => '12 - Kase (izdevumi)',
                        13 => '13 - Maksājumu konts (izdevumi)',
                        14 => '14 - Citi maksājuma līdzekļi (izdevumi)',
                        15 => '15 - Izdevumi (kopā)',
                        16 => '16 - Izdevumi, kas nav attiecināmi uz nodokļa aprēķināšanu',
                        17 => '17 - Subsīdijas',
                        18 => '18 - Izdevumi, kas nav saistīti ar saimn. darbību',
                        19 => '19 - Izdevumi par preču iegādi',
                        20 => '20 - Izdevumi par pakalpojumiem',
                        21 => '21 - Izdevumi par pamatlīdzekļiem',
                        22 => '22 - Izdevumi par nemateriālajiem ieguldījumiem',
                        23 => '23 - Izdevumi, kas saistīti ar darba samaksu (17.-20.)',
                        24 => '24 - Citi izdevumi',
                    ])
                    ->nullable()
                    ->helperText('VID žurnāla kolonnas numurs'),
                Forms\Components\Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'success' => 'INCOME',
                        'danger' => 'EXPENSE',
                    ]),
                Tables\Columns\TextColumn::make('vid_column')
                    ->label('VID Kolonna')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->sortable(),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
