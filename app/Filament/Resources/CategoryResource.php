<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use App\Models\JournalColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
                        'INCOME'   => 'Ieņēmumi',
                        'EXPENSE'  => 'Izdevumi',
                        'TRANSFER' => 'Pārskaitījums',
                    ])
                    ->live(),
                Forms\Components\Select::make('vid_column')
                    ->label('Žurnāla kolonna')
                    ->options(function (Get $get) {
                        $type = $get('type');
                        $query = JournalColumn::orderBy('sort_order');
                        if ($type === 'INCOME') {
                            $query->where('group', 'income');
                        } elseif ($type === 'EXPENSE') {
                            $query->where('group', 'expense');
                        } elseif ($type === 'TRANSFER') {
                            return [];
                        }
                        $opts = [];
                        foreach ($query->get() as $jc) {
                            $groupLabel = ($jc->group === 'income' ? 'Ieņēmumi' : 'Izdevumi') . ' → ' . $jc->abbr;
                            foreach (array_map('intval', $jc->vid_columns ?? []) as $vidNum) {
                                $opts[$groupLabel][$vidNum] = 'Kol.' . $vidNum . ' — ' . $jc->name;
                            }
                        }
                        return $opts;
                    })
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
