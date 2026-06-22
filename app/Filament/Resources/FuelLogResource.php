<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelLogResource\Pages;
use App\Models\FuelLog;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FuelLogResource extends Resource
{
    protected static ?string $model = FuelLog::class;

    protected static ?string $modelLabel = 'Uzpilde';

    protected static ?string $pluralModelLabel = 'Uzpildes';

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'Auto';

    protected static ?string $navigationLabel = 'Uzpildes';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('vehicle_id')
                            ->label('Auto')
                            ->relationship('vehicle')
                            ->getOptionLabelFromRecordUsing(fn (Vehicle $r) => $r->display_name)
                            ->default(fn () => Vehicle::where('is_active', true)->orderBy('sort_order')->value('id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('filled_at')
                            ->label('Datums')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->required(),

                        Forms\Components\TextInput::make('odometer')
                            ->label('Odometrs (km)')
                            ->numeric()
                            ->required()
                            ->suffix('km'),

                        Forms\Components\Select::make('fuel_type')
                            ->label('Veids')
                            ->options(FuelLog::FUEL_TYPES)
                            ->default('petrol')
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('liters')
                            ->label('Litri')
                            ->numeric()
                            ->required()
                            ->suffix('L')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalc($set, $get)),

                        Forms\Components\TextInput::make('price_per_liter')
                            ->label('Cena/L (€)')
                            ->numeric()
                            ->prefix('€')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalc($set, $get)),

                        Forms\Components\TextInput::make('total_cost')
                            ->label('Kopā (€)')
                            ->numeric()
                            ->required()
                            ->prefix('€'),

                        Forms\Components\Toggle::make('full_tank')
                            ->label('Pilna tvertne')
                            ->default(true)
                            ->helperText('Vajadzīgs precīzam patēriņa aprēķinam.'),

                        Forms\Components\TextInput::make('station')
                            ->label('DUS / vieta')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->label('Piezīmes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /** Aprēķina kopējo summu no litriem × cenas, ja abi ievadīti. */
    protected static function recalc(Forms\Set $set, Forms\Get $get): void
    {
        $liters = (float) $get('liters');
        $price = (float) $get('price_per_liter');

        if ($liters > 0 && $price > 0) {
            $set('total_cost', round($liters * $price, 2));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('filled_at')
                    ->label('Datums')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vehicle.display_name')
                    ->label('Auto')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('fuel_type')
                    ->label('Veids')
                    ->formatStateUsing(fn (string $state): string => FuelLog::FUEL_TYPES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'petrol' => 'warning',
                        'diesel' => 'gray',
                        'lpg'    => 'success',
                        default  => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('odometer')
                    ->label('Odometrs')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' km')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('liters')
                    ->label('Litri')
                    ->numeric(2, ',', ' ')
                    ->suffix(' L')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('price_per_liter')
                    ->label('Cena/L')
                    ->money('EUR')
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Kopā')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Kopā')->money('EUR')),

                Tables\Columns\TextColumn::make('consumption')
                    ->label('Patēriņš')
                    ->state(fn (FuelLog $record): string => $record->consumption !== null
                        ? number_format($record->consumption, 2, ',', ' ') . ' L/100'
                        : '—')
                    ->color('gray')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('full_tank')
                    ->label('Pilna')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('filled_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->label('Auto')
                    ->relationship('vehicle', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Vehicle $r) => $r->display_name),

                Tables\Filters\SelectFilter::make('fuel_type')
                    ->label('Veids')
                    ->options(FuelLog::FUEL_TYPES),
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
            ->modifyQueryUsing(fn ($query) => $query->with('vehicle'));
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFuelLogs::route('/'),
            'create' => Pages\CreateFuelLog::route('/create'),
            'edit'   => Pages\EditFuelLog::route('/{record}/edit'),
        ];
    }
}
