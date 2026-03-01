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
                    ->label('Žurnāla kolonna')
                    ->options([
                        'IEŅĒMUMI → Saimn. darb.' => [
                            4  => 'Kol.4 — Saimn. darb. (kase)',
                            5  => 'Kol.5 — Saimn. darb. (banka / maks. konts)',
                            6  => 'Kol.6 — Saimn. darb. (citi maks. līdzekļi)',
                        ],
                        'IEŅĒMUMI → Citas kolonnas' => [
                            10 => 'Kol.10 — Neapliekamie ieņēmumi',
                            8  => 'Kol.8 — Nav attiecināms uz nodokli',
                            9  => 'Kol.9 — Subsīdijas',
                        ],
                        'IZDEVUMI → Saistīti ar SD' => [
                            19 => 'Kol.19 — Saistīti ar SD: preču iegāde',
                            20 => 'Kol.20 — Saistīti ar SD: pakalpojumi',
                            21 => 'Kol.21 — Saistīti ar SD: pamatlīdzekļi',
                            22 => 'Kol.22 — Saistīti ar SD: nemateriālie ieguldījumi',
                            23 => 'Kol.23 — Saistīti ar SD: darba samaksa',
                        ],
                        'IZDEVUMI → Citas kolonnas' => [
                            18 => 'Kol.18 — Nesaistīti ar SD (Nesaist.)',
                            16 => 'Kol.16 — Nav attiecināms uz nodokli',
                            24 => 'Kol.24 — Citi izdevumi',
                        ],
                    ])
                    ->nullable()
                    ->searchable()
                    ->helperText('Norāda, kurā žurnāla analīzes kolonnā parādīsies darījums'),
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
                    ->label('Žurnāla kolonna')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        4  => 'Kol.4 Saimn.darb. (kase)',
                        5  => 'Kol.5 Saimn.darb. (banka)',
                        6  => 'Kol.6 Saimn.darb. (citi)',
                        8  => 'Kol.8 Nav attiec. (ienāk.)',
                        9  => 'Kol.9 Subsīdijas',
                        10 => 'Kol.10 Neapliekamie',
                        16 => 'Kol.16 Nav attiec. (izd.)',
                        18 => 'Kol.18 Nesaistīti ar SD',
                        19 => 'Kol.19 SD: preces',
                        20 => 'Kol.20 SD: pakalpojumi',
                        21 => 'Kol.21 SD: pamatlīdz.',
                        22 => 'Kol.22 SD: nemateriālie',
                        23 => 'Kol.23 SD: darba samaksa',
                        24 => 'Kol.24 Citi izdevumi',
                        default => $state ? 'Kol.' . $state : '—',
                    }),
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
