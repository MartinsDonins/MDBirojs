<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $modelLabel = 'Transportlīdzeklis';

    protected static ?string $pluralModelLabel = 'Transportlīdzekļi';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Auto';

    protected static ?string $navigationLabel = 'Transportlīdzekļi';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pamatdati')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nosaukums')
                                    ->placeholder('Piem. "Ģimenes auto"')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('make')
                                    ->label('Marka')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('model')
                                    ->label('Modelis')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('year')
                                    ->label('Izlaiduma gads')
                                    ->numeric()
                                    ->minValue(1900)
                                    ->maxValue((int) date('Y') + 1),

                                Forms\Components\TextInput::make('color')
                                    ->label('Krāsa')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('reg_number')
                                    ->label('Reģ. numurs')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('vin')
                                    ->label('VIN')
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Degviela')
                            ->schema([
                                Forms\Components\Select::make('primary_fuel')
                                    ->label('Galvenā degviela')
                                    ->options([
                                        'petrol' => 'Benzīns',
                                        'diesel' => 'Dīzelis',
                                        'lpg' => 'Gāze (LPG)',
                                        'hybrid' => 'Hibrīds',
                                        'electric' => 'Elektrība',
                                    ])
                                    ->default('petrol')
                                    ->required()
                                    ->native(false),

                                Forms\Components\Toggle::make('has_lpg')
                                    ->label('Aprīkots ar gāzes iekārtu (LPG)')
                                    ->live(),

                                Forms\Components\TextInput::make('tank_capacity')
                                    ->label('Degvielas tvertne (L)')
                                    ->numeric()
                                    ->suffix('L'),

                                Forms\Components\TextInput::make('lpg_capacity')
                                    ->label('Gāzes balons (L)')
                                    ->numeric()
                                    ->suffix('L')
                                    ->visible(fn (Forms\Get $get): bool => (bool) $get('has_lpg')),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Nobraukums')
                            ->schema([
                                Forms\Components\TextInput::make('initial_odometer')
                                    ->label('Sākuma odometrs (km)')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('km')
                                    ->helperText('Odometrs uzskaites sākumā.'),
                            ]),

                        Forms\Components\Section::make('Derīguma termiņi')
                            ->schema([
                                Forms\Components\DatePicker::make('insurance_expires_at')
                                    ->label('OCTA derīga līdz')
                                    ->native(false)
                                    ->displayFormat('d.m.Y'),

                                Forms\Components\DatePicker::make('casco_expires_at')
                                    ->label('KASKO derīga līdz')
                                    ->native(false)
                                    ->displayFormat('d.m.Y'),

                                Forms\Components\DatePicker::make('inspection_expires_at')
                                    ->label('Tehniskā apskate līdz')
                                    ->native(false)
                                    ->displayFormat('d.m.Y'),
                            ]),

                        Forms\Components\Section::make('Cits')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Aktīvs')
                                    ->default(true),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Piezīmes')
                                    ->rows(3),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Auto')
                    ->searchable(['name', 'make', 'model', 'reg_number'])
                    ->sortable(['make'])
                    ->weight('bold')
                    ->description(fn (Vehicle $record): ?string => $record->reg_number),

                Tables\Columns\TextColumn::make('primary_fuel')
                    ->label('Degviela')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'petrol' => 'Benzīns',
                        'diesel' => 'Dīzelis',
                        'lpg' => 'Gāze',
                        'hybrid' => 'Hibrīds',
                        'electric' => 'Elektrība',
                        default => $state,
                    })
                    ->badge()
                    ->color('gray')
                    ->description(fn (Vehicle $record): ?string => $record->has_lpg ? '+ LPG gāze' : null),

                Tables\Columns\TextColumn::make('current_odometer')
                    ->label('Nobraukums')
                    ->state(fn (Vehicle $record): string => number_format($record->current_odometer, 0, ',', ' ').' km')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('consumption')
                    ->label('Patēriņš')
                    ->state(function (Vehicle $record): string {
                        $parts = [];
                        if (($p = $record->averageConsumption('petrol')) !== null) {
                            $parts[] = 'B '.number_format($p, 1, ',', ' ');
                        }
                        if (($d = $record->averageConsumption('diesel')) !== null) {
                            $parts[] = 'D '.number_format($d, 1, ',', ' ');
                        }
                        if (($g = $record->averageConsumption('lpg')) !== null) {
                            $parts[] = 'G '.number_format($g, 1, ',', ' ');
                        }

                        return $parts ? implode(' · ', $parts).' L/100' : '—';
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('outstanding_amount')
                    ->label('Jāsamaksā')
                    ->state(fn (Vehicle $record): string => $record->outstanding_amount > 0
                        ? number_format($record->outstanding_amount, 2, ',', ' ').' €'
                        : '—')
                    ->badge()
                    ->color(fn (Vehicle $record): string => $record->outstanding_amount > 0 ? 'danger' : 'gray')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('inspection_expires_at')
                    ->label('Tehn. apskate')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->color(fn (Vehicle $record): string => $record->inspection_expires_at && $record->inspection_expires_at->isPast() ? 'danger' : 'gray')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktīvs')
                    ->boolean()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktīvs'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
