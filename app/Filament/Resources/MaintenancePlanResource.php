<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenancePlanResource\Pages;
use App\Models\MaintenancePlan;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenancePlanResource extends Resource
{
    protected static ?string $model = MaintenancePlan::class;

    protected static ?string $modelLabel = 'Apkopes plāns';

    protected static ?string $pluralModelLabel = 'Apkopju plāns';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Auto';

    protected static ?string $navigationLabel = 'Apkopju plāns';

    protected static ?int $navigationSort = 4;

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

                        Forms\Components\TextInput::make('title')
                            ->label('Nosaukums')
                            ->placeholder('Piem. "Eļļas maiņa"')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Apraksts')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Fieldset::make('Intervāls (vismaz viens)')
                            ->schema([
                                Forms\Components\TextInput::make('interval_km')
                                    ->label('Ik pēc (km)')
                                    ->numeric()
                                    ->suffix('km'),

                                Forms\Components\TextInput::make('interval_months')
                                    ->label('Ik pēc (mēneši)')
                                    ->numeric()
                                    ->suffix('mēn.'),
                            ])
                            ->columns(2),

                        Forms\Components\Fieldset::make('Pēdējoreiz veikts')
                            ->schema([
                                Forms\Components\TextInput::make('last_done_odometer')
                                    ->label('Odometrs (km)')
                                    ->numeric()
                                    ->suffix('km'),

                                Forms\Components\DatePicker::make('last_done_at')
                                    ->label('Datums')
                                    ->native(false)
                                    ->displayFormat('d.m.Y'),
                            ])
                            ->columns(2),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktīvs')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->label('Piezīmes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('due_status')
                    ->label('Statuss')
                    ->state(fn (MaintenancePlan $record): string => MaintenancePlan::dueStatusLabel($record->due_status))
                    ->badge()
                    ->color(fn (MaintenancePlan $record): string => MaintenancePlan::dueStatusColor($record->due_status)),

                Tables\Columns\TextColumn::make('title')
                    ->label('Nosaukums')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (MaintenancePlan $record): ?string => $record->description),

                Tables\Columns\TextColumn::make('vehicle.display_name')
                    ->label('Auto')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('interval')
                    ->label('Intervāls')
                    ->state(function (MaintenancePlan $record): string {
                        $parts = [];
                        if ($record->interval_km) {
                            $parts[] = number_format($record->interval_km, 0, ',', ' ').' km';
                        }
                        if ($record->interval_months) {
                            $parts[] = $record->interval_months.' mēn.';
                        }

                        return $parts ? implode(' / ', $parts) : '—';
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('next_due_odometer')
                    ->label('Nākamreiz (km)')
                    ->state(function (MaintenancePlan $record): string {
                        if ($record->next_due_odometer === null) {
                            return '—';
                        }
                        $km = number_format($record->next_due_odometer, 0, ',', ' ').' km';
                        $remaining = $record->km_remaining;
                        if ($remaining !== null) {
                            $km .= $remaining < 0
                                ? ' (−'.number_format(abs($remaining), 0, ',', ' ').')'
                                : ' (+'.number_format($remaining, 0, ',', ' ').')';
                        }

                        return $km;
                    })
                    ->color(fn (MaintenancePlan $record): string => $record->km_remaining !== null && $record->km_remaining < 0 ? 'danger' : 'gray')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('next_due_date')
                    ->label('Nākamreiz (datums)')
                    ->state(fn (MaintenancePlan $record): string => $record->next_due_date?->format('d.m.Y') ?? '—')
                    ->color(fn (MaintenancePlan $record): string => $record->days_remaining !== null && $record->days_remaining < 0 ? 'danger' : 'gray')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktīvs')
                    ->boolean()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->label('Auto')
                    ->relationship('vehicle', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Vehicle $r) => $r->display_name),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktīvs'),
            ])
            ->actions([
                Tables\Actions\Action::make('markDone')
                    ->label('Veikta tagad')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Atzīmēt apkopi kā veiktu?')
                    ->modalDescription('Pēdējoreiz veikts tiks atjaunots uz šodienu un pašreizējo nobraukumu.')
                    ->action(function (MaintenancePlan $record): void {
                        $record->update([
                            'last_done_at' => now(),
                            'last_done_odometer' => $record->vehicle->current_odometer,
                        ]);
                    }),

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
            'index' => Pages\ListMaintenancePlans::route('/'),
            'create' => Pages\CreateMaintenancePlan::route('/create'),
            'edit' => Pages\EditMaintenancePlan::route('/{record}/edit'),
        ];
    }
}
