<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalColumnResource\Pages;
use App\Models\JournalColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class JournalColumnResource extends Resource
{
    protected static ?string $model = JournalColumn::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Žurnāla kolonnas';

    protected static ?string $navigationGroup = 'Iestatījumi';

    protected static ?string $modelLabel = 'Žurnāla kolonna';

    protected static ?string $pluralModelLabel = 'Žurnāla kolonnas';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('group')
                    ->label('Grupa')
                    ->options([
                        'income'  => 'Ieņēmumi',
                        'expense' => 'Izdevumi',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Nosaukums')
                    ->required()
                    ->maxLength(100),

                Forms\Components\TextInput::make('abbr')
                    ->label('Saīsinājums')
                    ->required()
                    ->maxLength(30),

                Forms\Components\TextInput::make('vid_columns_text')
                    ->label('VID kolonnas (nr., atdalītas ar komatiem)')
                    ->helperText('Piemēram: 4,5,6')
                    ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                        if ($record) {
                            $component->state(implode(',', array_map('intval', $record->vid_columns ?? [])));
                        }
                    })
                    ->required(),

                Forms\Components\Hidden::make('vid_columns'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Kārtošanas nr.')
                    ->integer()
                    ->default(10)
                    ->required(),

                Forms\Components\Toggle::make('is_visible')
                    ->label('Rādīt žurnālā')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                Tables\Columns\TextColumn::make('group')
                    ->label('Grupa')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'income' ? 'Ieņēmumi' : 'Izdevumi')
                    ->color(fn ($state) => $state === 'income' ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nosaukums')
                    ->searchable(),

                Tables\Columns\TextColumn::make('abbr')
                    ->label('Saīsinājums'),

                Tables\Columns\TextColumn::make('vid_columns')
                    ->label('VID kolonnas')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Redzama')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('Grupa')
                    ->options([
                        'income'  => 'Ieņēmumi',
                        'expense' => 'Izdevumi',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_visible')
                    ->label(fn ($record) => $record->is_visible ? 'Paslēpt' : 'Rādīt')
                    ->icon(fn ($record) => $record->is_visible ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn ($record) => $record->is_visible ? 'warning' : 'success')
                    ->action(function ($record) {
                        $record->update(['is_visible' => !$record->is_visible]);
                        Notification::make()
                            ->title($record->is_visible ? 'Kolonna paslēpta' : 'Kolonna atklāta')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Parse vid_columns_text → array of ints
                        if (isset($data['vid_columns_text'])) {
                            $data['vid_columns'] = array_values(array_filter(
                                array_map('intval', array_map('trim', explode(',', $data['vid_columns_text'])))
                            ));
                            unset($data['vid_columns_text']);
                        }
                        return $data;
                    }),
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
            'index' => Pages\ListJournalColumns::route('/'),
        ];
    }
}
