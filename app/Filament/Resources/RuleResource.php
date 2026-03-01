<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuleResource\Pages;
use App\Filament\Resources\RuleResource\RelationManagers;
use App\Models\Rule;
use App\Services\AutoApprovalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RuleResource extends Resource
{
    protected static ?string $model = Rule::class;

    protected static ?string $modelLabel = 'Kārtula';

    protected static ?string $pluralModelLabel = 'Kārtulas';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nosaukums')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                
                Forms\Components\TextInput::make('priority')
                    ->label('Prioritāte')
                    ->numeric()
                    ->default(0)
                    ->required(),
                
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktīva')
                    ->default(true)
                    ->required(),

                Forms\Components\Section::make('Šie VISI jāizpildās (AND)')
                    ->description('Darījumam jāatbilst VISIEM šiem kritērijiem')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Repeater::make('and_criteria')
                            ->label('')
                            ->schema(self::criterionSchema())
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('+ Pievienot AND kritēriju')
                            ->reorderable(false),
                    ]),

                Forms\Components\Section::make('Vismaz VIENS no šiem (OR)')
                    ->description('Papildus AND grupai — darījumam jāatbilst vismaz vienam no šiem kritērijiem (var atstāt tukšu)')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Repeater::make('or_criteria')
                            ->label('')
                            ->schema(self::criterionSchema())
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('+ Pievienot OR kritēriju')
                            ->reorderable(false),
                    ]),

                Forms\Components\Section::make('Darbība (Action)')
                    ->schema([
                        Forms\Components\Select::make('action.category_id')
                            ->label('Iestatīt kategoriju')
                            ->options(\App\Models\Category::pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('action.type')
                            ->label('Iestatīt tipu')
                            ->options([
                                'INCOME' => 'Ieņēmumi',
                                'EXPENSE' => 'Izdevumi',
                                'TRANSFER' => 'Pārskaitījums',
                            ]),
                        Forms\Components\Select::make('action.reverse_account_id')
                            ->label('Automātiski izveidot pretējo darījumu kontā')
                            ->options(\App\Models\Account::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Ja iestatīts — kārtulas aktivizēšanās laikā tiks izveidots pretēja tipa darījums norādītajā kontā un abi darījumi tiks sasaistīti.'),

                        Forms\Components\Toggle::make('action.auto_link_matching')
                            ->label('Auto-sasaiste: meklēt atbilstošu darījumu')
                            ->helperText('Ja ieslēgts — meklēs darījumu CITĀ kontā ar vienādu datumu (±1 diena), summu un aprakstu un izveidos sasaisti. Noderīgi pārskaitījumiem starp saviem kontiem.')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    /** Shared schema for a single criterion row (used in both AND and OR repeaters). */
    public static function criterionSchema(): array
    {
        return [
            Forms\Components\Select::make('field')
                ->label('Lauks')
                ->options([
                    'description'          => 'Apraksts',
                    'counterparty_name'    => 'Darījuma partneris',
                    'counterparty_account' => 'Partnera konta nr.',
                    'amount'               => 'Summa',
                    'reference'            => 'Atsauce',
                    'type'                 => 'Darījuma veids',
                    'account_name'         => 'Konta nosaukums (mans)',
                ])
                ->required()
                ->live(),

            Forms\Components\Select::make('operator')
                ->label('Operators')
                ->options(fn (Get $get) => match ($get('field')) {
                    'amount' => [
                        'equals' => '=',
                        'gt'     => '>',
                        'lt'     => '<',
                    ],
                    'type', 'account_name' => [
                        'equals'      => 'Ir vienāds ar',
                        'contains'    => 'Satur',
                    ],
                    default => [
                        'contains'    => 'Satur',
                        'equals'      => 'Ir vienāds ar',
                        'starts_with' => 'Sākas ar',
                        'ends_with'   => 'Beidzas ar',
                    ],
                })
                ->default('contains')
                ->required(),

            Forms\Components\Select::make('value')
                ->label('Vērtība')
                ->options([
                    'INCOME'   => 'Ieņēmumi (INCOME)',
                    'EXPENSE'  => 'Izdevumi (EXPENSE)',
                    'TRANSFER' => 'Pārskaitījums (TRANSFER)',
                    'FEE'      => 'Komisija (FEE)',
                ])
                ->visible(fn (Get $get) => $get('field') === 'type')
                ->required(fn (Get $get) => $get('field') === 'type'),

            Forms\Components\TextInput::make('value')
                ->label('Vērtība')
                ->placeholder(fn (Get $get) => match ($get('field')) {
                    'amount'       => 'Piemēram: 100',
                    'account_name' => 'Piemēram: SEB banka',
                    default        => 'Ievadiet vērtību...',
                })
                ->visible(fn (Get $get) => $get('field') !== 'type')
                ->required(fn (Get $get) => $get('field') !== 'type'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioritāte')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nosaukums')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktīva')
                    ->boolean(),
                Tables\Columns\TextColumn::make('criteria')
                    ->label('Kritēriji')
                    ->formatStateUsing(function ($state) {
                        if (!is_array($state)) return '0';
                        $and = count($state['and_criteria'] ?? (isset($state['and_criteria']) ? [] : $state));
                        $or  = count($state['or_criteria']  ?? []);
                        if (isset($state['and_criteria']) || isset($state['or_criteria'])) {
                            return "AND:{$and} OR:{$or}";
                        }
                        return count($state) . ' (AND)';
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Izveidots')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('run')
                    ->label('Izpildīt')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Rule $record) => 'Izpildīt: ' . $record->name)
                    ->modalDescription('Kārtula tiks piemērota visiem DRAFT un NEEDS_REVIEW darījumiem, kas atbilst kritērijiem. Vai turpināt?')
                    ->action(function (Rule $record) {
                        $service = app(AutoApprovalService::class);
                        $stats   = $service->applyCustomRule($record);

                        Notification::make()
                            ->title('Kārtula izpildīta')
                            ->body("Piemērota: {$stats['applied']} darījumi (pārskatīti: {$stats['processed']})")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('view_transactions')
                    ->label('Darījumi')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->url(fn (Rule $record) => \App\Filament\Resources\TransactionResource::getUrl('index')
                        . '?' . http_build_query(['tableFilters' => ['applied_rule_id' => ['value' => $record->id]]])),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
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
            'index' => Pages\ListRules::route('/'),
            'create' => Pages\CreateRule::route('/create'),
            'edit' => Pages\EditRule::route('/{record}/edit'),
        ];
    }
}
