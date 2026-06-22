<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VidDocumentResource\Pages;
use App\Models\VidDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VidDocumentResource extends Resource
{
    protected static ?string $model = VidDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'VID dokumenti';

    protected static ?string $modelLabel = 'VID dokuments';

    protected static ?string $pluralModelLabel = 'VID dokumenti';

    protected static ?string $navigationGroup = 'VID un deklarācijas';

    protected static ?int $navigationSort = 3;

    /**
     * Year options: current year down to 2015 (newest first).
     *
     * @return array<int, int>
     */
    protected static function yearOptions(): array
    {
        $current = (int) date('Y');
        $years   = range($current, 2015);
        return array_combine($years, $years);
    }

    protected static function statusOptions(): array
    {
        return array_combine(VidDocument::STATUSES, VidDocument::STATUSES);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('year')
                    ->label('Gads')
                    ->options(static::yearOptions())
                    ->default((int) date('Y'))
                    ->required(),

                Forms\Components\TextInput::make('document_name')
                    ->label('Dokumenta nosaukums')
                    ->placeholder('IIN avansa maksājumu aprēķins no saimnieciskās darbības')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('status_code')
                    ->label('Statusa kods')
                    ->placeholder('05')
                    ->maxLength(10),

                Forms\Components\Select::make('status')
                    ->label('Statuss')
                    ->options(static::statusOptions())
                    ->searchable(),

                Forms\Components\DatePicker::make('submitted_at')
                    ->label('Iesniegšanas datums')
                    ->native(false)
                    ->displayFormat('d.m.Y'),

                Forms\Components\TextInput::make('link')
                    ->label('Saite (EDS)')
                    ->url()
                    ->prefixIcon('heroicon-o-link')
                    ->maxLength(255),

                Forms\Components\Textarea::make('notes')
                    ->label('Piezīmes')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('year')
                    ->label('Gads')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statuss')
                    ->badge()
                    ->color(fn ($state) => VidDocument::statusColor($state))
                    ->formatStateUsing(fn ($state, $record) => trim(($record->status_code ? $record->status_code . ' ' : '') . ($state ?? '')))
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_name')
                    ->label('Dokumenta nosaukums')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Iesniegts')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('link')
                    ->label('Saite')
                    ->url(fn ($record) => $record->link, true)
                    ->formatStateUsing(fn ($state) => $state ? 'Atvērt' : null)
                    ->color('primary')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Piezīmes')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(),
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->label('Gads')
                    ->options(static::yearOptions()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statuss')
                    ->options(static::statusOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver(),
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
            'index' => Pages\ListVidDocuments::route('/'),
        ];
    }
}
