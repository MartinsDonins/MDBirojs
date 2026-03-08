<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $modelLabel = 'Uzdevums';

    protected static ?string $pluralModelLabel = 'Uzdevumi';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Uzdevumi';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Left column (2/3) ─────────────────────────────────────
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pamatdati')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Nosaukums')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('description')
                                    ->label('Apraksts')
                                    ->rows(3)
                                    ->maxLength(2000)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Section::make('Apakšuzdevumi')
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
                                            ->label('Uzdevums')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(11),
                                    ])
                                    ->columns(12)
                                    ->addActionLabel('+ Pievienot apakšuzdevumu')
                                    ->reorderable('sort_order')
                                    ->cloneable(false)
                                    ->collapsible(false)
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                            ])
                            ->collapsible(),

                        Forms\Components\Section::make('Piezīmes')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label('')
                                    ->rows(4)
                                    ->maxLength(5000)
                                    ->placeholder('Papildinformācija, norādes, saites...')
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 2]),

                // ── Right column (1/3) ────────────────────────────────────
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Statuss & Prioritāte')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Statuss')
                                    ->options([
                                        'open'        => 'Atvērts',
                                        'in_progress' => 'Procesā',
                                        'completed'   => 'Pabeigts',
                                        'cancelled'   => 'Atcelts',
                                    ])
                                    ->default('open')
                                    ->required()
                                    ->native(false),

                                Forms\Components\Select::make('priority')
                                    ->label('Prioritāte')
                                    ->options([
                                        'low'    => 'Zema',
                                        'medium' => 'Vidēja',
                                        'high'   => 'Augsta',
                                        'urgent' => 'Steidzams',
                                    ])
                                    ->default('medium')
                                    ->required()
                                    ->native(false),
                            ]),

                        Forms\Components\Section::make('Ieplānošana')
                            ->schema([
                                Forms\Components\DateTimePicker::make('due_at')
                                    ->label('Izpildes termiņš')
                                    ->nullable()
                                    ->native(false)
                                    ->displayFormat('d.m.Y H:i')
                                    ->seconds(false),

                                Forms\Components\Select::make('recurrence_type')
                                    ->label('Atkārtošanās')
                                    ->options([
                                        'none'    => 'Nav',
                                        'daily'   => 'Katru dienu',
                                        'weekly'  => 'Katru nedēļu',
                                        'monthly' => 'Katru mēnesi',
                                        'yearly'  => 'Katru gadu',
                                    ])
                                    ->default('none')
                                    ->required()
                                    ->native(false),
                            ]),

                        Forms\Components\Section::make('Papildinformācija')
                            ->schema([
                                Forms\Components\Select::make('task_category_id')
                                    ->label('Kategorija')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nosaukums')
                                            ->required()
                                            ->maxLength(100),
                                        Forms\Components\ColorPicker::make('color')
                                            ->label('Krāsa')
                                            ->default('#6366f1'),
                                    ])
                                    ->createOptionModalHeading('Jauna kategorija'),

                                Forms\Components\Select::make('transaction_id')
                                    ->label('Saistītais darījums')
                                    ->options(
                                        Transaction::query()
                                            ->orderByDesc('occurred_at')
                                            ->limit(200)
                                            ->get()
                                            ->mapWithKeys(fn ($t) => [
                                                $t->id => ($t->occurred_at?->format('d.m.Y') ?? '—') . ' · ' . ($t->description ?: 'Nav apraksta') . ' · ' . number_format(abs($t->amount), 2, ',', ' ') . ' €',
                                            ])
                                    )
                                    ->searchable()
                                    ->nullable()
                                    ->placeholder('Nav saistīta darījuma'),
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
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prio')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low'    => '▼ Zema',
                        'medium' => '■ Vidēja',
                        'high'   => '▲ Augsta',
                        'urgent' => '⚡ Steidzams',
                        default  => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low'    => 'info',
                        'medium' => 'warning',
                        'high'   => 'danger',
                        'urgent' => 'danger',
                        default  => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Nosaukums')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (Task $record): ?string => $record->description ? Str::limit($record->description, 80) : null),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija')
                    ->badge()
                    ->color(fn ($record) => 'gray')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('due_at')
                    ->label('Termiņš')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->color(fn (Task $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->formatStateUsing(function (Task $record): string {
                        if (! $record->due_at) {
                            return '—';
                        }
                        $date = $record->due_at->format('d.m.Y');
                        if ($record->isOverdue()) {
                            return '⚠ ' . $date;
                        }
                        if ($record->due_at->isToday()) {
                            return '📅 Šodien';
                        }
                        return $date;
                    }),

                Tables\Columns\TextColumn::make('items_progress')
                    ->label('Apakšuzdevumi')
                    ->state(function (Task $record): string {
                        $total = $record->items->count();
                        if ($total === 0) {
                            return '—';
                        }
                        $done = $record->items->where('is_completed', true)->count();
                        return "{$done}/{$total}";
                    })
                    ->badge()
                    ->color(function (Task $record): string {
                        $total = $record->items->count();
                        if ($total === 0) {
                            return 'gray';
                        }
                        $done = $record->items->where('is_completed', true)->count();
                        return $done === $total ? 'success' : 'warning';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statuss')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open'        => 'Atvērts',
                        'in_progress' => 'Procesā',
                        'completed'   => 'Pabeigts',
                        'cancelled'   => 'Atcelts',
                        default       => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open'        => 'info',
                        'in_progress' => 'warning',
                        'completed'   => 'success',
                        'cancelled'   => 'gray',
                        default       => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('recurrence_type')
                    ->label('Atkārtošanās')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'daily'   => '↺ Katru dienu',
                        'weekly'  => '↺ Katru ned.',
                        'monthly' => '↺ Katru mēn.',
                        'yearly'  => '↺ Katru gadu',
                        default   => '—',
                    })
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('due_at', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statuss')
                    ->options([
                        'open'        => 'Atvērts',
                        'in_progress' => 'Procesā',
                        'completed'   => 'Pabeigts',
                        'cancelled'   => 'Atcelts',
                    ]),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioritāte')
                    ->options([
                        'low'    => 'Zema',
                        'medium' => 'Vidēja',
                        'high'   => 'Augsta',
                        'urgent' => 'Steidzams',
                    ]),

                Tables\Filters\SelectFilter::make('task_category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name'),

                Tables\Filters\Filter::make('overdue')
                    ->label('Nokavēti')
                    ->query(fn (Builder $query) => $query
                        ->where('due_at', '<', now())
                        ->whereNotIn('status', ['completed', 'cancelled'])
                    ),

                Tables\Filters\Filter::make('due_today')
                    ->label('Termiņš šodien')
                    ->query(fn (Builder $query) => $query
                        ->whereDate('due_at', today())
                        ->whereNotIn('status', ['completed', 'cancelled'])
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('Pabeigt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Task $record) => ! in_array($record->status, ['completed', 'cancelled']))
                    ->requiresConfirmation()
                    ->modalHeading('Atzīmēt kā pabeigtu?')
                    ->modalDescription(fn (Task $record) => $record->recurrence_type !== 'none'
                        ? 'Uzdevums tiks atzīmēts kā pabeigts un automātiski tiks izveidots nākošais atkārtojums.'
                        : null
                    )
                    ->action(fn (Task $record) => $record->update(['status' => 'completed'])),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Atzīmēt kā pabeigtu')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['status' => 'completed'])),

                    Tables\Actions\BulkAction::make('mark_open')
                        ->label('Atzīmēt kā atvērtu')
                        ->icon('heroicon-o-arrow-path')
                        ->action(fn ($records) => $records->each->update(['status' => 'open', 'completed_at' => null])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->with(['category', 'items']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit'   => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
