<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InvestmentSummary extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    protected static ?string $navigationIcon    = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel   = 'Ieguldījumi';
    protected static ?string $title             = 'Ieguldījumu reģistrs';
    protected static string  $view              = 'filament.pages.investment-summary';
    protected static ?int    $navigationSort    = 5;

    // Computed summary stats shown in the header
    public float  $totalInvested   = 0;
    public int    $investorCount   = 0;
    public int    $transactionCount = 0;

    public function mount(): void
    {
        $this->refreshStats();
    }

    // ---------------------------------------------------------------------------
    // Stats
    // ---------------------------------------------------------------------------

    public function refreshStats(): void
    {
        $base = $this->investmentQuery();

        $this->totalInvested    = (float) (clone $base)->sum('amount_eur');
        $this->investorCount    = (clone $base)->distinct()->count('counterparty_name');
        $this->transactionCount = (clone $base)->count();
    }

    /**
     * Base query: transactions where description or category name contains "ieguldīj".
     */
    protected function investmentQuery(): Builder
    {
        return Transaction::query()
            ->where(function (Builder $q) {
                $q->where('description', 'ILIKE', '%ieguldīj%')
                  ->orWhereHas('category', fn (Builder $cq) =>
                        $cq->where('name', 'ILIKE', '%ieguldīj%')
                  );
            });
    }

    // ---------------------------------------------------------------------------
    // Filament Table
    // ---------------------------------------------------------------------------

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->investmentQuery()->with(['account', 'category', 'cashOrder']))
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Datums')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Konts')
                    ->badge()
                    ->color(fn ($record) => match ($record->account?->type) {
                        'CASH'    => 'success',
                        'BANK'    => 'primary',
                        'PAYSERA' => 'warning',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('counterparty_name')
                    ->label('Ieguldītājs')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Apraksts')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('amount_eur')
                    ->label('Summa (EUR)')
                    ->money('EUR')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('cashOrder.number')
                    ->label('Kases orderis')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statuss')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'COMPLETED'    => 'success',
                        'DRAFT'        => 'gray',
                        'NEEDS_REVIEW' => 'warning',
                        default        => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'COMPLETED'    => 'Pabeigts',
                        'DRAFT'        => 'Melnraksts',
                        'NEEDS_REVIEW' => 'Jāpārskata',
                        default        => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_id')
                    ->label('Konts')
                    ->options(Account::all()->pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statuss')
                    ->options([
                        'COMPLETED'    => 'Pabeigts',
                        'DRAFT'        => 'Melnraksts',
                        'NEEDS_REVIEW' => 'Jāpārskata',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Transaction $record) => route('filament.admin.resources.transactions.edit', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    // ---------------------------------------------------------------------------
    // Header actions
    // ---------------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_investment')
                ->label('Pievienot ieguldījumu')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Forms\Components\Section::make('Ieguldījuma dati')
                        ->schema([
                            Forms\Components\Select::make('account_id')
                                ->label('Konts')
                                ->options(Account::all()->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->helperText('Bankas konts vai kase'),

                            Forms\Components\DatePicker::make('occurred_at')
                                ->label('Datums')
                                ->required()
                                ->default(now())
                                ->maxDate(now()),

                            Forms\Components\TextInput::make('counterparty_name')
                                ->label('Ieguldītājs')
                                ->required()
                                ->default('MĀRTIŅŠ DONIŅŠ')
                                ->helperText('Personas vai uzņēmuma nosaukums'),

                            Forms\Components\TextInput::make('amount')
                                ->label('Summa (EUR)')
                                ->required()
                                ->numeric()
                                ->minValue(0.01)
                                ->prefix('€'),

                            Forms\Components\TextInput::make('description')
                                ->label('Apraksts')
                                ->default('naudas ieguldījums')
                                ->required(),

                            Forms\Components\TextInput::make('reference')
                                ->label('Atsauce / dok. nr.')
                                ->placeholder('Piem.: 2024010100001'),
                        ])
                        ->columns(2),
                ])
                ->action(function (array $data): void {
                    $amount = abs((float) $data['amount']);

                    Transaction::create([
                        'account_id'       => $data['account_id'],
                        'occurred_at'      => $data['occurred_at'],
                        'amount'           => $amount,
                        'currency'         => 'EUR',
                        'amount_eur'       => $amount,
                        'exchange_rate'    => 1,
                        'counterparty_name' => $data['counterparty_name'],
                        'description'      => $data['description'],
                        'reference'        => $data['reference'] ?? null,
                        'type'             => 'INCOME',
                        'status'           => 'COMPLETED',
                        // fingerprint is auto-generated in Transaction::boot()
                    ]);

                    $this->refreshStats();

                    Notification::make()
                        ->title('Ieguldījums pievienots')
                        ->body("Ieguldīts: €{$amount} no {$data['counterparty_name']}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
