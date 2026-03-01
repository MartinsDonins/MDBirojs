<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class IncomeExpenseJournal extends Page implements HasTable, HasActions, HasForms
{
    use InteractsWithTable;
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Ieņēmumu/Izdevumu Žurnāls';
    
    protected static ?string $title = 'Saimnieciskās darbības ieņēmumu un izdevumu uzskaites žurnāls';

    protected static string $view = 'filament.pages.income-expense-journal';
    
    protected static ?int $navigationSort = 3;

    public ?int $selectedYear = null; // null = show year list
    public ?int $selectedMonth = null; // null = show year summary (if year selected), 1-12 = show month detail
    
    public array $summary = [
        'total_income' => 0,
        'total_expense' => 0,
        'balance' => 0,
    ];
    
    public array $yearlySummary = [];
    public array $monthlySummary = [];
    public $accounts;
    public bool $showOnlyInvalid = false;

    // VID kolonnu karte: kurām kategorijām ir attēlota analīzes kolonna
    protected const INCOME_MAPPED_COLS  = [4, 5, 6, 8, 10];
    protected const EXPENSE_MAPPED_COLS = [16, 18, 19, 20, 21, 22, 23];

    public function mount(): void
    {
        // Start with overview of all years
        $this->selectedYear = null; 
        $this->selectedMonth = null;
        
        $this->accounts = \App\Models\Account::all();
        
        $this->calculateYearlySummary();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getMonthDetailQuery())
            ->columns([
                Tables\Columns\TextColumn::make('row_number')
                    ->label('Nr.')
                    ->rowIndex(),
                    
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Datums')
                    ->date('d.m.Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('counterparty_name')
                    ->label('Apraksts')
                    ->searchable()
                    ->limit(40),
                    
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija')
                    ->badge()
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('income')
                    ->label('Ieņēmumi (EUR)')
                    ->money('EUR')
                    ->getStateUsing(fn ($record) => $record->type === 'INCOME' ? $record->amount : null)
                    ->alignEnd(),
                    
                Tables\Columns\TextColumn::make('expense')
                    ->label('Izdevumi (EUR)')
                    ->money('EUR')
                    ->getStateUsing(fn ($record) => $record->type === 'EXPENSE' ? abs($record->amount) : null)
                    ->alignEnd(),
            ])
            ->defaultSort('occurred_at', 'asc')
            ->paginated([25, 50, 100]);
    }

    public array $rows = [];
    public array $opening_balances = [];
    public array $closing_balances = [];

    public function calculateMonthData(): void
    {
        if (!$this->selectedYear || !$this->selectedMonth) {
            $this->rows = [];
            $this->opening_balances = [];
            $this->closing_balances = [];
            return;
        }

        $startDate = \Carbon\Carbon::createFromDate($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $periodStart = $startDate;
        $periodEnd = $endDate;

        // 1. Calculate Opening Balances for this period
        $this->opening_balances = [];
        foreach($this->accounts as $acc) {
            // Start with the Account's initial balance (from DB)
            $initialBalance = $acc->balance ?? 0;
            
            // Sum of all transactions before period start
            $transactionsSum = Transaction::where('account_id', $acc->id)
                ->where('occurred_at', '<', $periodStart)
                ->sum(DB::raw("CASE WHEN type = 'INCOME' THEN amount ELSE -amount END"));
                
            $this->opening_balances[$acc->id] = $initialBalance + $transactionsSum;
        }

        // 2. Get transactions for the selected period
        $transactions = Transaction::with(['account', 'category', 'linkedTransaction.account'])
            ->where('occurred_at', '>=', $periodStart)
            ->where('occurred_at', '<=', $periodEnd)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $data = [];
        $runningEntryNumber = 1; 
        
        // Initialize running balances with opening balances
        $currentBalances = $this->opening_balances;

        foreach ($transactions as $transaction) {
            // Update balance for the specific account
            if ($transaction->type === 'INCOME') {
                $currentBalances[$transaction->account_id] += $transaction->amount;
            } else {
                 $currentBalances[$transaction->account_id] -= abs($transaction->amount);
            }

            $row = [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status, // DRAFT, COMPLETED, NEEDS_REVIEW
                'entry_number' => $runningEntryNumber++,
                'date' => $transaction->occurred_at->format('d.m.Y'),
                'document_details' => $transaction->reference, // Document name/number
                'partner' => $transaction->counterparty_name,
                'description' => $transaction->description,
                'category' => $transaction->category?->name,
                'category_vid_column' => $transaction->category?->vid_column,
                'linked_transaction_id' => $transaction->linked_transaction_id,
                'linked_account_name' => $transaction->linkedTransaction?->account?->name,
                'is_mapped' => $this->isTransactionMapped($transaction),
                
                // Account specific data
                'transaction_account_id' => $transaction->account_id,
                'transaction_amount' => $transaction->amount,
                'transaction_type' => $transaction->type,
                
                // Snapshot of balances AFTER this transaction
                'account_balances' => $currentBalances, 
            ];
            
            $data[] = $row;
        }

        $this->rows = $data;
        $this->closing_balances = $currentBalances;
    }

    protected function getViewData(): array
    {
        return [];
    }

    protected function getMonthDetailQuery(): Builder
    {
        return Transaction::query()
            ->with(['category', 'account'])
            ->where('status', 'COMPLETED')
            ->whereIn('type', ['INCOME', 'EXPENSE'])
            ->whereYear('occurred_at', $this->selectedYear)
            ->whereMonth('occurred_at', $this->selectedMonth);
    }
    protected function calculateYearlySummary(): void
    {
        $yearlyData = Transaction::query()
            ->where('status', 'COMPLETED')
            ->selectRaw('
                EXTRACT(YEAR FROM occurred_at) as year,
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = ? THEN ABS(amount) ELSE 0 END) as expense
            ', ['INCOME', 'EXPENSE'])
            ->groupBy('year')
            ->orderBy('year', 'asc') // Calculate chronologically for running balance
            ->get();

        $this->yearlySummary = [];
        $runningBalance = 0;

        foreach ($yearlyData as $data) {
            $income = $data->income ?? 0;
            $expense = $data->expense ?? 0;
            $annualResult = $income - $expense;
            $runningBalance += $annualResult;

            // Prepend to array to show newest first, but keep running balance correct
            array_unshift($this->yearlySummary, [
                'year' => (int) $data->year,
                'income' => $income,
                'expense' => $expense,
                'result' => $annualResult,
                'end_balance' => $runningBalance,
            ]);
        }
    }

    protected function calculateMonthlySummary(): void
    {
        if (!$this->selectedYear) return;

        // 1. Calculate Opening Balance for the year
        $totalInitialBalance = \App\Models\Account::sum('balance');

        $openingBalance = $totalInitialBalance + (Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereYear('occurred_at', '<', $this->selectedYear)
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -ABS(amount) ELSE 0 END) as balance', ['INCOME', 'EXPENSE'])
            ->value('balance') ?? 0);

        // 2. Get monthly totals
        $monthlyData = Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereYear('occurred_at', $this->selectedYear)
            ->selectRaw('
                EXTRACT(MONTH FROM occurred_at) as month_number,
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = ? THEN ABS(amount) ELSE 0 END) as expense
            ', ['INCOME', 'EXPENSE'])
            ->groupBy('month_number')
            ->orderBy('month_number')
            ->get();

        // 3. Per-category breakdown per month (for analysis columns)
        $categoryBreakdown = Transaction::query()
            ->where('transactions.status', 'COMPLETED')
            ->whereYear('transactions.occurred_at', $this->selectedYear)
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("
                EXTRACT(MONTH FROM transactions.occurred_at) as month_number,
                transactions.type,
                COALESCE(categories.name, '— nav kategorijas') as category_name,
                categories.vid_column,
                SUM(ABS(transactions.amount)) as total
            ")
            ->groupBy(
                DB::raw('EXTRACT(MONTH FROM transactions.occurred_at)'),
                'transactions.type',
                'categories.name',
                'categories.vid_column'
            )
            ->orderBy('month_number')
            ->get();

        $monthNames = [
            1 => 'Janvāris', 2 => 'Februāris', 3 => 'Marts', 4 => 'Aprīlis',
            5 => 'Maijs', 6 => 'Jūnijs', 7 => 'Jūlijs', 8 => 'Augusts',
            9 => 'Septembris', 10 => 'Oktobris', 11 => 'Novembris', 12 => 'Decembris'
        ];

        $this->monthlySummary = [];
        $runningBalance = $openingBalance;

        for ($month = 1; $month <= 12; $month++) {
            $data    = $monthlyData->firstWhere('month_number', $month);
            $income  = $data->income ?? 0;
            $expense = $data->expense ?? 0;
            $runningBalance += ($income - $expense);

            $monthCats   = $categoryBreakdown->filter(fn ($c) => (int) round((float) $c->month_number) === $month);
            $incomeCats  = $monthCats->where('type', 'INCOME');
            $expenseCats = $monthCats->where('type', 'EXPENSE');

            $incomeSaimn      = (float) $incomeCats->filter(fn ($c) => in_array((int) $c->vid_column, [4, 5, 6]))->sum('total');
            $incomeNeapl      = (float) $incomeCats->filter(fn ($c) => (int) $c->vid_column === 10)->sum('total');
            $incomeNavAttiec  = (float) $incomeCats->filter(fn ($c) => (int) $c->vid_column === 8)->sum('total');
            $incomeKopaa      = $incomeSaimn + $incomeNeapl + $incomeNavAttiec;

            $expenseSaistiti  = (float) $expenseCats->filter(fn ($c) => in_array((int) $c->vid_column, [19, 20, 21, 22, 23]))->sum('total');
            $expenseNesaist   = (float) $expenseCats->filter(fn ($c) => (int) $c->vid_column === 18)->sum('total');
            $expenseNavAttiec = (float) $expenseCats->filter(fn ($c) => (int) $c->vid_column === 16)->sum('total');
            $expenseKopaa     = $expenseSaistiti + $expenseNesaist + $expenseNavAttiec;

            $this->monthlySummary[] = [
                'month'              => $monthNames[$month],
                'month_number'       => $month,
                'income'             => $income,
                'expense'            => $expense,
                'balance'            => $runningBalance,
                // Analysis column totals
                'income_saimn'       => $incomeSaimn,
                'income_neapl'       => $incomeNeapl,
                'income_nav_attiec'  => $incomeNavAttiec,
                'income_kopaa'       => $incomeKopaa,
                'expense_saistiti'   => $expenseSaistiti,
                'expense_nesaist'    => $expenseNesaist,
                'expense_nav_attiec' => $expenseNavAttiec,
                'expense_kopaa'      => $expenseKopaa,
                // Per-category detail (sorted: INCOME first, then EXPENSE; alphabetically within each)
                'categories'         => $monthCats
                    ->sortBy(fn ($c) => ($c->type === 'INCOME' ? '0' : '1') . ($c->category_name ?? ''))
                    ->map(fn ($c) => [
                        'name'       => $c->category_name,
                        'type'       => $c->type,
                        'vid_column' => (int) ($c->vid_column ?? 0),
                        'total'      => (float) $c->total,
                    ])
                    ->values()
                    ->toArray(),
            ];
        }
    }

    protected function calculateSummary(): void
    {
        if (!$this->selectedYear) return;

        $summary = Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereYear('occurred_at', $this->selectedYear)
            ->selectRaw('
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = ? THEN ABS(amount) ELSE 0 END) as total_expense
            ', ['INCOME', 'EXPENSE'])
            ->first();

        // Calculate opening balance again for the summary card's total balance
        $totalInitialBalance = \App\Models\Account::sum('balance');
        
        $openingBalance = $totalInitialBalance + (Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereYear('occurred_at', '<', $this->selectedYear)
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -ABS(amount) ELSE 0 END) as balance', ['INCOME', 'EXPENSE'])
            ->value('balance') ?? 0);

        $totalIncome = $summary->total_income ?? 0;
        $totalExpense = $summary->total_expense ?? 0;

        $this->summary = [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $openingBalance + $totalIncome - $totalExpense,
        ];
    }

    public function selectYear(int $year): void
    {
        $this->selectedYear = $year;
        $this->selectedMonth = null;
        $this->calculateYearlySummary();
        $this->calculateMonthlySummary();
    }

    public function viewMonthDetails(int $month): void
    {
        $this->selectedMonth = $month;
        $this->calculateMonthData();
    }

    public function mountCategoryModal($transactionId)
    {
        try {
            \Illuminate\Support\Facades\Log::info('Mounting Category Modal for ID: ' . $transactionId);
            $this->mountAction('editCategory', ['transaction_id' => $transactionId]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error mounting category modal: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function mountTransactionModal($transactionId)
    {
        $this->mountAction('editTransaction', ['transaction_id' => $transactionId]);
    }

    public function mountLinkModal($transactionId)
    {
        $this->mountAction('linkTransaction', ['transaction_id' => $transactionId]);
    }

    public function mountOpeningBalanceModal($accountId)
    {
        $this->mountAction('editOpeningBalance', ['account_id' => $accountId]);
    }

    public function mountStatusModal($transactionId)
    {
        $this->mountAction('editStatus', ['transaction_id' => $transactionId]);
    }

    public function editCategoryAction(): Action
    {
        return Action::make('editCategory')
            ->label('Mainīt kategoriju')
            ->modalWidth('md')
            ->form([
                Forms\Components\Select::make('category_id')
                    ->label('Kategorija')
                    ->options(\App\Models\Category::query()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Nosaukums'),
                        Forms\Components\Select::make('type')
                            ->options([
                                'INCOME' => 'Ieņēmumi',
                                'EXPENSE' => 'Izdevumi',
                            ])
                            ->label('Veids'),
                        Forms\Components\Select::make('vid_column')
                            ->label('Žurnāla kolonna')
                            ->options([
                                'IEŅĒMUMI → Saimn. darb.' => [4 => 'Kol.4 Saimn.darb. (kase)', 5 => 'Kol.5 Saimn.darb. (banka)', 6 => 'Kol.6 Saimn.darb. (citi)'],
                                'IEŅĒMUMI → Citas' => [10 => 'Kol.10 Neapliekamie', 8 => 'Kol.8 Nav attiec.', 9 => 'Kol.9 Subsīdijas'],
                                'IZDEVUMI → Saistīti ar SD' => [19 => 'Kol.19 SD: preces', 20 => 'Kol.20 SD: pakalpojumi', 21 => 'Kol.21 SD: pamatlīdz.', 22 => 'Kol.22 SD: nemateriālie', 23 => 'Kol.23 SD: darba samaksa'],
                                'IZDEVUMI → Citas' => [18 => 'Kol.18 Nesaistīti ar SD', 16 => 'Kol.16 Nav attiec.', 24 => 'Kol.24 Citi izdevumi'],
                            ])
                            ->nullable()
                            ->searchable()
                    ])
                    ->createOptionUsing(function (array $data) {
                        return \App\Models\Category::create($data)->id;
                    }),
            ])
            ->fillForm(fn (array $arguments) => [
                'category_id' => \App\Models\Transaction::find($arguments['transaction_id'])?->category_id,
            ])
            ->action(function (array $data, array $arguments) {
                $transaction = \App\Models\Transaction::find($arguments['transaction_id']);
                if ($transaction) {
                    $transaction->update(['category_id' => $data['category_id']]);
                    $this->calculateMonthData();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Kategorija atjaunota')
                        ->success()
                        ->send();
                }
            });
    }

    public function editTransactionAction(): Action
    {
        return Action::make('editTransaction')
            ->label('Rediģēt darījumu')
            ->modalWidth('md')
            ->form([
                Forms\Components\TextInput::make('counterparty_name')
                    ->label('Partneris'),
                Forms\Components\TextInput::make('description')
                    ->label('Apraksts')
                    ->required(),
                Forms\Components\TextInput::make('reference')
                    ->label('Dokumenta detaļas'),
                Forms\Components\TextInput::make('notes')
                    ->label('Piezīmes (Sasaite)'),
            ])
            ->fillForm(fn (array $arguments) => \App\Models\Transaction::find($arguments['transaction_id'])?->toArray())
            ->action(function (array $data, array $arguments) {
                $transaction = \App\Models\Transaction::find($arguments['transaction_id']);
                if ($transaction) {
                    $transaction->update($data);
                    $this->calculateMonthData();

                    \Filament\Notifications\Notification::make()
                        ->title('Darījums saglabāts')
                        ->success()
                        ->send();
                }
            });
    }

    public function linkTransactionAction(): Action
    {
        return Action::make('linkTransaction')
            ->label('Pārvaldīt sasaisti')
            ->modalWidth('lg')
            ->form(function (array $arguments) {
                $transaction = Transaction::with('linkedTransaction.account')->find($arguments['transaction_id']);
                $currentAccountId = $transaction?->account_id;
                $hasLink = $transaction?->linked_transaction_id !== null;

                $otherAccounts = \App\Models\Account::where('id', '!=', $currentAccountId)
                    ->pluck('name', 'id')
                    ->toArray();

                $actionOptions = [
                    'link_existing' => 'Sasaistīt ar esošu darījumu',
                    'create_new'    => 'Izveidot jaunu saistīto darījumu',
                ];
                if ($hasLink) {
                    $actionOptions['unlink'] = 'Noņemt sasaisti';
                }

                return [
                    Forms\Components\Placeholder::make('current_link_info')
                        ->label('Pašreizējā sasaiste')
                        ->content(function () use ($transaction) {
                            if (!$transaction?->linked_transaction_id) {
                                return 'Nav sasaistes';
                            }
                            $linked = $transaction->linkedTransaction;
                            return '↔ ' . ($linked?->account?->name ?? '?') . ' — ' . $linked?->occurred_at?->format('d.m.Y') . ' — ' . number_format(abs($linked?->amount ?? 0), 2, ',', ' ') . ' EUR';
                        }),

                    Forms\Components\Select::make('action_type')
                        ->label('Darbība')
                        ->options($actionOptions)
                        ->default('link_existing')
                        ->required()
                        ->live()
                        ->native(false),

                    // --- Link existing ---
                    Forms\Components\Select::make('target_account_id')
                        ->label('Konts')
                        ->options($otherAccounts)
                        ->required()
                        ->live()
                        ->native(false)
                        ->hidden(fn (Forms\Get $get) => $get('action_type') !== 'link_existing'),

                    Forms\Components\Select::make('existing_transaction_id')
                        ->label('Darījums')
                        ->options(function (Forms\Get $get) use ($transaction) {
                            $accountId = $get('target_account_id');
                            if (!$accountId) return [];
                            return Transaction::where('account_id', $accountId)
                                ->whereBetween('occurred_at', [
                                    $transaction?->occurred_at?->subDays(60),
                                    $transaction?->occurred_at?->addDays(60),
                                ])
                                ->orderBy('occurred_at')
                                ->get()
                                ->mapWithKeys(fn ($t) => [
                                    $t->id => $t->occurred_at->format('d.m.Y') . ' | ' . number_format(abs($t->amount), 2, ',', ' ') . ' EUR | ' . ($t->counterparty_name ?? $t->description ?? '—')
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->hidden(fn (Forms\Get $get) => $get('action_type') !== 'link_existing'),

                    // --- Create new ---
                    Forms\Components\Select::make('new_account_id')
                        ->label('Konts (jaunajam darījumam)')
                        ->options($otherAccounts)
                        ->required()
                        ->native(false)
                        ->hidden(fn (Forms\Get $get) => $get('action_type') !== 'create_new'),

                    Forms\Components\DatePicker::make('new_date')
                        ->label('Datums')
                        ->default($transaction?->occurred_at)
                        ->required()
                        ->hidden(fn (Forms\Get $get) => $get('action_type') !== 'create_new'),

                    Forms\Components\TextInput::make('new_amount')
                        ->label('Summa (EUR)')
                        ->numeric()
                        ->default(fn () => abs($transaction?->amount ?? 0))
                        ->required()
                        ->hidden(fn (Forms\Get $get) => $get('action_type') !== 'create_new'),

                    Forms\Components\Select::make('new_type')
                        ->label('Veids')
                        ->options([
                            'INCOME'  => 'Ieņēmumi',
                            'EXPENSE' => 'Izdevumi',
                        ])
                        ->default(fn () => $transaction?->type === 'INCOME' ? 'EXPENSE' : 'INCOME')
                        ->required()
                        ->native(false)
                        ->hidden(fn (Forms\Get $get) => $get('action_type') !== 'create_new'),

                    Forms\Components\TextInput::make('new_description')
                        ->label('Apraksts')
                        ->default(fn () => $transaction?->description)
                        ->hidden(fn (Forms\Get $get) => $get('action_type') !== 'create_new'),
                ];
            })
            ->fillForm(fn (array $arguments) => [
                'action_type' => 'link_existing',
            ])
            ->action(function (array $data, array $arguments) {
                $transaction = Transaction::find($arguments['transaction_id']);
                if (!$transaction) return;

                if ($data['action_type'] === 'unlink') {
                    // Remove bidirectional link
                    if ($transaction->linked_transaction_id) {
                        Transaction::where('id', $transaction->linked_transaction_id)
                            ->update(['linked_transaction_id' => null]);
                    }
                    $transaction->update(['linked_transaction_id' => null]);

                    \Filament\Notifications\Notification::make()
                        ->title('Sasaiste noņemta')
                        ->success()
                        ->send();

                } elseif ($data['action_type'] === 'link_existing') {
                    $targetId = $data['existing_transaction_id'];
                    // Remove old links if any
                    if ($transaction->linked_transaction_id) {
                        Transaction::where('id', $transaction->linked_transaction_id)
                            ->update(['linked_transaction_id' => null]);
                    }
                    // Set bidirectional link
                    $transaction->update(['linked_transaction_id' => $targetId]);
                    Transaction::where('id', $targetId)
                        ->update(['linked_transaction_id' => $transaction->id]);

                    \Filament\Notifications\Notification::make()
                        ->title('Sasaiste izveidota')
                        ->success()
                        ->send();

                } elseif ($data['action_type'] === 'create_new') {
                    $newTransaction = Transaction::create([
                        'account_id'       => $data['new_account_id'],
                        'occurred_at'      => $data['new_date'],
                        'amount'           => $data['new_amount'],
                        'amount_eur'       => $data['new_amount'],
                        'currency'         => 'EUR',
                        'exchange_rate'    => 1,
                        'type'             => $data['new_type'],
                        'status'           => 'COMPLETED',
                        'description'      => $data['new_description'] ?? $transaction->description,
                        'counterparty_name' => $transaction->counterparty_name,
                        'linked_transaction_id' => $transaction->id,
                    ]);
                    $transaction->update(['linked_transaction_id' => $newTransaction->id]);

                    \Filament\Notifications\Notification::make()
                        ->title('Jauns saistītais darījums izveidots')
                        ->success()
                        ->send();
                }

                $this->calculateMonthData();
            });
    }

    public function editOpeningBalanceAction(): Action
    {
        return Action::make('editOpeningBalance')
            ->label('Labot sākuma atlikumu')
            ->modalWidth('sm')
            ->form(function (array $arguments) {
                $account = \App\Models\Account::find($arguments['account_id']);
                return [
                    Forms\Components\Placeholder::make('account_name')
                        ->label('Konts')
                        ->content($account?->name ?? '—'),
                    Forms\Components\TextInput::make('balance')
                        ->label('Sākuma atlikums (EUR)')
                        ->numeric()
                        ->prefix('€')
                        ->required()
                        ->helperText('Bilance pirms pirmā darījuma šajā kontā. Izmantojiet, lai iestatītu vēsturisko sākuma atlikumu.'),
                ];
            })
            ->fillForm(fn (array $arguments) => [
                'balance' => \App\Models\Account::find($arguments['account_id'])?->balance ?? 0,
            ])
            ->action(function (array $data, array $arguments) {
                $account = \App\Models\Account::find($arguments['account_id']);
                if ($account) {
                    $account->update(['balance' => $data['balance']]);
                    $this->calculateMonthData();

                    \Filament\Notifications\Notification::make()
                        ->title('Sākuma atlikums saglabāts')
                        ->success()
                        ->send();
                }
            });
    }

    public function backToAllYears(): void
    {
        $this->selectedYear = null;
        $this->selectedMonth = null;
        $this->calculateYearlySummary();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->selectedMonth !== null) {
            // View: Month Details -> Back to Year Summary
            $actions[] = \Filament\Actions\Action::make('back')
                ->label('Atpakaļ uz ' . $this->selectedYear . '. gada kopsavilkumu')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action('backToYearSummary');
        } elseif ($this->selectedYear !== null) {
            // View: Year Summary -> Back to All Years
            $actions[] = \Filament\Actions\Action::make('back_all')
                ->label('Atpakaļ uz gadu sarakstu')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action('backToAllYears');
        }

        // Export actions available only when a year is selected
        if ($this->selectedYear !== null) {
            $actions[] = \Filament\Actions\Action::make('export_excel')
                ->label('Eksportēt Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action('exportExcel');
                
            $actions[] = \Filament\Actions\Action::make('export_pdf')
                ->label('Eksportēt PDF')
                ->icon('heroicon-o-document-text')
                ->color('danger')
                ->action('exportPdf');
        }

        // Add transaction available when year is selected
        if ($this->selectedYear !== null) {
            $actions[] = $this->createTransactionAction();
        }

        // Always register edit actions (they are triggered programmatically from table rows)
        $actions[] = $this->editCategoryAction();
        $actions[] = $this->editTransactionAction();
        $actions[] = $this->editStatusAction();
        $actions[] = $this->linkTransactionAction();
        $actions[] = $this->editOpeningBalanceAction();

        return $actions;
    }

    public function exportExcel()
    {
        \Filament\Notifications\Notification::make()
            ->title('Excel eksports')
            ->body('Excel eksporta funkcionalitāte tiks pievienota nākamajā versijā')
            ->info()
            ->send();
    }

    public function exportPdf()
    {
        \Filament\Notifications\Notification::make()
            ->title('PDF eksports')
            ->body('PDF eksporta funkcionalitāte tiks pievienota nākamajā versijā')
            ->info()
            ->send();
    }

    public function editStatusAction(): Action
    {
        return Action::make('editStatus')
            ->label('Mainīt statusu')
            ->modalWidth('sm')
            ->form([
                Forms\Components\Select::make('status')
                    ->label('Statuss')
                    ->options([
                        'DRAFT' => 'Melnraksts',
                        'COMPLETED' => 'Apstiprināts',
                        'NEEDS_REVIEW' => 'Nepieciešama pārbaude',
                    ])
                    ->required()
                    ->native(false),
            ])
            ->fillForm(fn (array $arguments) => [
                'status' => Transaction::find($arguments['transaction_id'])?->status,
            ])
            ->action(function (array $data, array $arguments) {
                $transaction = Transaction::find($arguments['transaction_id']);
                if ($transaction) {
                    $transaction->update(['status' => $data['status']]);
                    $this->calculateMonthData();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Statuss mainīts')
                        ->success()
                        ->send();
                }
            });
    }

    public function toggleInvalidFilter(): void
    {
        $this->showOnlyInvalid = !$this->showOnlyInvalid;
    }

    protected function isTransactionMapped(Transaction $transaction): bool
    {
        $vid = (int) ($transaction->category?->vid_column ?? 0);

        if ($transaction->type === 'INCOME') {
            return in_array($vid, self::INCOME_MAPPED_COLS);
        }

        if ($transaction->type === 'EXPENSE') {
            return in_array($vid, self::EXPENSE_MAPPED_COLS);
        }

        return true; // TRANSFER / FEE — nav jāvalidē
    }

    public function backToYearSummary()
    {
        $this->selectedMonth = null;
        $this->calculateYearlySummary();
        $this->calculateMonthlySummary();
    }

    public function mountCreateTransactionModal()
    {
        try {
            \Illuminate\Support\Facades\Log::info('Mounting Create Transaction Modal');
            $this->mountAction('createTransaction');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error mounting Create Transaction modal: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            throw $e;
        }
    }


    public function createTransactionAction(): Action
    {
        return Action::make('createTransaction')
            ->label('Pievienot jaunu darījumu')
            ->modalWidth('lg')
            ->form([
                Forms\Components\DatePicker::make('occurred_at')
                    ->label('Datums')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('account_id')
                    ->label('Konts')
                    ->options(\App\Models\Account::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Veids')
                            ->options([
                                'INCOME' => 'Ieņēmumi',
                                'EXPENSE' => 'Izdevumi',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('currency')
                            ->label('Valūta')
                            ->options([
                                'EUR' => 'EUR — Euro',
                                'LVL' => 'LVL — Latvijas lats',
                                'USD' => 'USD — ASV dolārs',
                                'GBP' => 'GBP — Britu mārciņa',
                            ])
                            ->default('EUR')
                            ->required()
                            ->live()
                            ->native(false),
                    ]),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label(fn (Forms\Get $get) => 'Summa (' . ($get('currency') ?: 'EUR') . ')')
                            ->numeric()
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $currency = $get('currency') ?: 'EUR';
                                $rate = (float) ($get('exchange_rate') ?: 1);
                                if ($currency === 'EUR') {
                                    $set('amount_eur', $state);
                                } elseif ($rate > 0) {
                                    $set('amount_eur', round((float) $state / $rate, 2));
                                }
                            }),
                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('Kurss (1 EUR = X valūtā)')
                            ->numeric()
                            ->default(1)
                            ->live(debounce: 500)
                            ->hidden(fn (Forms\Get $get) => ($get('currency') ?: 'EUR') === 'EUR')
                            ->helperText('LVL: 1 EUR = 0.702804 LVL')
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $amount = (float) ($get('amount') ?: 0);
                                $rate = (float) ($state ?: 1);
                                if ($rate > 0) {
                                    $set('amount_eur', round($amount / $rate, 2));
                                }
                            }),
                    ]),
                Forms\Components\TextInput::make('amount_eur')
                    ->label('Summa EUR')
                    ->numeric()
                    ->prefix('€')
                    ->required()
                    ->hidden(fn (Forms\Get $get) => ($get('currency') ?: 'EUR') === 'EUR')
                    ->helperText('Aprēķināts automātiski, bet var labot manuāli'),
                Forms\Components\Select::make('category_id')
                    ->label('Kategorija')
                    ->options(\App\Models\Category::pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('counterparty_name')
                    ->label('Partneris'),
                Forms\Components\TextInput::make('description')
                    ->label('Apraksts')
                    ->required(),
                Forms\Components\TextInput::make('reference')
                    ->label('Dokumenta nr.'),
                Forms\Components\Select::make('status')
                    ->label('Statuss')
                    ->options([
                        'DRAFT' => 'Melnraksts',
                        'COMPLETED' => 'Apstiprināts',
                        'NEEDS_REVIEW' => 'Nepieciešama pārbaude',
                    ])
                    ->default('COMPLETED')
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data) {
                // If EUR, amount_eur = amount
                if (($data['currency'] ?? 'EUR') === 'EUR') {
                    $data['amount_eur'] = $data['amount'];
                    $data['exchange_rate'] = 1;
                }
                \App\Models\Transaction::create($data);
                $this->calculateMonthData();
                \Filament\Notifications\Notification::make()
                    ->title('Darījums pievienots')
                    ->success()
                    ->send();
            });
    }

    public function getTitle(): string
    {
        if ($this->selectedMonth !== null) {
            $monthNames = [
                1 => 'JANVĀRIS', 2 => 'FEBRUĀRIS', 3 => 'MARTS', 4 => 'APRĪLIS',
                5 => 'MAIJS', 6 => 'JŪNIJS', 7 => 'JŪLIJS', 8 => 'AUGUSTS',
                9 => 'SEPTEMBRIS', 10 => 'OKTOBRIS', 11 => 'NOVEMBRIS', 12 => 'DECEMBRIS'
            ];
            return $monthNames[$this->selectedMonth] . ' ' . $this->selectedYear;
        }

        if ($this->selectedYear !== null) {
            return 'Saimnieciskās darbības ieņēmumu un izdevumu uzskaites žurnāls - ' . $this->selectedYear . '. gads';
        }

        return 'Saimnieciskās darbības ieņēmumu un izdevumu uzskaites žurnāls (Gadu Pārskats)';
    }
}
