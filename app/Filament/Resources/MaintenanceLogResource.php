<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceLogResource\Pages;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceLogResource extends Resource
{
    protected static ?string $model = MaintenanceLog::class;

    protected static ?string $modelLabel = 'Apkope / remonts';

    protected static ?string $pluralModelLabel = 'Apkopes un remonti';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Auto';

    protected static ?string $navigationLabel = 'Apkopes un remonti';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pamatdati')
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
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('type')
                                    ->label('Tips')
                                    ->options(MaintenanceLog::TYPES)
                                    ->default('service')
                                    ->required()
                                    ->native(false),

                                Forms\Components\DatePicker::make('performed_at')
                                    ->label('Datums')
                                    ->default(now())
                                    ->native(false)
                                    ->displayFormat('d.m.Y')
                                    ->required(),

                                Forms\Components\TextInput::make('odometer')
                                    ->label('Odometrs (km)')
                                    ->numeric()
                                    ->suffix('km'),

                                Forms\Components\TextInput::make('provider')
                                    ->label('Serviss / izpildītājs')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make('description')
                                    ->label('Apraksts / paveiktie darbi')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Darbu pozīcijas')
                            ->description('Atsevišķi darbi vai detaļas ar izmaksām.')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->label('')
                                    ->relationship()
                                    ->schema([
                                        Forms\Components\Checkbox::make('is_completed')
                                            ->label('')
                                            ->inline()
                                            ->columnSpan(1),
                                        Forms\Components\TextInput::make('title')
                                            ->hiddenLabel()
                                            ->required()
                                            ->placeholder('Darbs vai detaļa...')
                                            ->columnSpan(8),
                                        Forms\Components\TextInput::make('cost')
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->prefix('€')
                                            ->default(0)
                                            ->columnSpan(3),
                                    ])
                                    ->columns(12)
                                    ->addActionLabel('+ Pievienot pozīciju')
                                    ->reorderable('sort_order')
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                            ])
                            ->collapsible(),

                        Forms\Components\Section::make('Pielikumi')
                            ->schema([
                                Forms\Components\FileUpload::make('attachments')
                                    ->label('Foto / dokumenti')
                                    ->multiple()
                                    ->directory('auto/apkopes')
                                    ->downloadable()
                                    ->openable()
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Budžets')
                            ->schema([
                                Forms\Components\TextInput::make('total_cost')
                                    ->label('Kopējā summa')
                                    ->numeric()
                                    ->prefix('€')
                                    ->default(0)
                                    ->required(),

                                Forms\Components\TextInput::make('amount_paid')
                                    ->label('Samaksāts')
                                    ->numeric()
                                    ->prefix('€')
                                    ->default(0)
                                    ->required()
                                    ->helperText('Atlikums (kopā − samaksāts) tiek aprēķināts automātiski.'),
                            ]),

                        Forms\Components\Section::make('Statuss')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Statuss')
                                    ->options(MaintenanceLog::STATUSES)
                                    ->default('completed')
                                    ->required()
                                    ->native(false),

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
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Datums')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tips')
                    ->formatStateUsing(fn (string $state): string => MaintenanceLog::TYPES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'service'    => 'success',
                        'repair'     => 'danger',
                        'inspection' => 'info',
                        'tires'      => 'warning',
                        default      => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Nosaukums')
                    ->searchable()
                    ->wrap()
                    ->description(fn (MaintenanceLog $record): ?string => $record->provider),

                Tables\Columns\TextColumn::make('vehicle.display_name')
                    ->label('Auto')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Kopā')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Kopā')->money('EUR')),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Samaksāts')
                    ->money('EUR')
                    ->alignEnd()
                    ->toggleable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Samaksāts')->money('EUR')),

                Tables\Columns\TextColumn::make('outstanding')
                    ->label('Jāsamaksā')
                    ->state(fn (MaintenanceLog $record): string => $record->outstanding > 0
                        ? number_format($record->outstanding, 2, ',', ' ') . ' €'
                        : '—')
                    ->badge()
                    ->color(fn (MaintenanceLog $record): string => $record->outstanding > 0 ? 'danger' : 'gray')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Apmaksa')
                    ->state(fn (MaintenanceLog $record): string => MaintenanceLog::paymentStatusLabel($record->payment_status))
                    ->badge()
                    ->color(fn (MaintenanceLog $record): string => MaintenanceLog::paymentStatusColor($record->payment_status)),
            ])
            ->defaultSort('performed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->label('Auto')
                    ->relationship('vehicle', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Vehicle $r) => $r->display_name),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tips')
                    ->options(MaintenanceLog::TYPES),

                Tables\Filters\Filter::make('unpaid')
                    ->label('Nesamaksātie')
                    ->query(fn ($query) => $query->whereColumn('amount_paid', '<', 'total_cost')),
            ])
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label('Apmaksāts')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (MaintenanceLog $record): bool => $record->outstanding > 0)
                    ->requiresConfirmation()
                    ->modalHeading('Atzīmēt kā pilnībā apmaksātu?')
                    ->action(fn (MaintenanceLog $record) => $record->update(['amount_paid' => $record->total_cost])),

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
            'index'  => Pages\ListMaintenanceLogs::route('/'),
            'create' => Pages\CreateMaintenanceLog::route('/create'),
            'edit'   => Pages\EditMaintenanceLog::route('/{record}/edit'),
        ];
    }
}
