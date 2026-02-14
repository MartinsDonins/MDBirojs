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

class IncomeExpenseJournal extends Page implements HasTable
{
    use InteractsWithTable;

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

    public function mount(): void
    {
        // Start with overview of all years
        $this->selectedYear = null; 
        $this->selectedMonth = null;
        
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

    public function getData(): array
    {
        $query = Transaction::query()
            ->with(['account', 'category'])
            ->orderBy('occurred_at')
            ->orderBy('id');

        if ($this->selectedYear && $this->selectedMonth) {
            $startDate = \Carbon\Carbon::createFromDate($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            
            // We need to fetch all transactions before the end of the selected month 
            // to correctly calculate running balances up to that point.
            // However, for display, we only show rows from the selected month.
            // A clearer approach: 
            // 1. Get opening balances for all accounts at start of month
            // 2. Get transactions for the month
            
            $periodStart = $startDate;
            $periodEnd = $endDate;
        } else {
            // Default to current month if not selected
            $periodStart = now()->startOfMonth();
            $periodEnd = now()->endOfMonth();
        }

        // Get all active accounts
        $accounts = \App\Models\Account::all();
        
        // 1. Calculate Opening Balances for this period
        $openingBalances = [];
        foreach($accounts as $acc) {
            // Sum of all transactions before period start
            $openingBalances[$acc->id] = Transaction::where('account_id', $acc->id)
                ->where('occurred_at', '<', $periodStart)
                ->sum(DB::raw("CASE WHEN type = 'INCOME' THEN amount ELSE -amount END"));
        }

        // 2. Get transactions for the selected period
        $transactions = Transaction::with(['account', 'category'])
            ->where('occurred_at', '>=', $periodStart)
            ->where('occurred_at', '<=', $periodEnd)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $data = [];
        $runningEntryNumber = 1; // TODO: Should this be sequential from start of year? For now, relative to view.
        
        // Initialize running balances with opening balances
        $currentBalances = $openingBalances;

        foreach ($transactions as $transaction) {
            // Update balance for the specific account
            if ($transaction->type === 'INCOME') {
                $currentBalances[$transaction->account_id] += $transaction->amount;
            } else {
                 $currentBalances[$transaction->account_id] -= abs($transaction->amount);
            }

            $row = [
                'entry_number' => $runningEntryNumber++,
                'date' => $transaction->occurred_at->format('d.m.Y'),
                'document_details' => $transaction->reference, // Document name/number
                'partner' => $transaction->counterparty_name,
                'description' => $transaction->description,
                'category' => $transaction->category?->name,
                'category_vid_column' => $transaction->category?->vid_column,
                
                // Account specific data
                'transaction_account_id' => $transaction->account_id,
                'transaction_amount' => $transaction->amount,
                'transaction_type' => $transaction->type,
                
                // Snapshot of balances AFTER this transaction
                'account_balances' => $currentBalances, 
            ];
            
            $data[] = $row;
        }

        return [
            'rows' => $data,
            'accounts' => $accounts,
            'opening_balances' => $openingBalances,
            'closing_balances' => $currentBalances,
        ];
    }
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
        $openingBalance = Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereYear('occurred_at', '<', $this->selectedYear)
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -ABS(amount) ELSE 0 END) as balance', ['INCOME', 'EXPENSE'])
            ->value('balance') ?? 0;

        // 2. Get monthly data for selected year
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

        $monthNames = [
            1 => 'Janvāris', 2 => 'Februāris', 3 => 'Marts', 4 => 'Aprīlis',
            5 => 'Maijs', 6 => 'Jūnijs', 7 => 'Jūlijs', 8 => 'Augusts',
            9 => 'Septembris', 10 => 'Oktobris', 11 => 'Novembris', 12 => 'Decembris'
        ];

        $this->monthlySummary = [];
        $runningBalance = $openingBalance;

        for ($month = 1; $month <= 12; $month++) {
            $data = $monthlyData->firstWhere('month_number', $month);
            
            $income = $data->income ?? 0;
            $expense = $data->expense ?? 0;
            $monthBalance = $income - $expense;
            $runningBalance += $monthBalance;

            $this->monthlySummary[] = [
                'month' => $monthNames[$month],
                'month_number' => $month,
                'income' => $income,
                'expense' => $expense,
                'balance' => $runningBalance,
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
        $openingBalance = Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereYear('occurred_at', '<', $this->selectedYear)
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -ABS(amount) ELSE 0 END) as balance', ['INCOME', 'EXPENSE'])
            ->value('balance') ?? 0;

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
        $this->calculateSummary();
        $this->calculateMonthlySummary();
    }

    public function viewMonthDetails(int $month): void
    {
        $this->selectedMonth = $month;
        $this->calculateVidMonthDetail();
    }

    public function backToYearSummary(): void
    {
        $this->selectedMonth = null;
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

    public array $vidMonthDetail = [];

    protected function calculateVidMonthDetail(): void
    {
        if (!$this->selectedYear || !$this->selectedMonth) {
            $this->vidMonthDetail = [];
            return;
        }

        $transactions = Transaction::query()
            ->with(['category', 'account'])
            ->where('status', 'COMPLETED')
            ->whereYear('occurred_at', $this->selectedYear)
            ->whereMonth('occurred_at', $this->selectedMonth)
            ->orderBy('occurred_at', 'asc')
            ->get();

        $this->vidMonthDetail = [];
        $entryNumber = 1;

        foreach ($transactions as $transaction) {
            $vidColumn = $transaction->category?->vid_column;
            
            $this->vidMonthDetail[] = [
                'entry_number' => $entryNumber++,
                'date' => $transaction->occurred_at->format('d.m.Y'),
                'description' => $transaction->description ?? $transaction->counterparty_name,
                'account' => $transaction->account?->name,
                'amount' => $transaction->amount,
                'vid_column' => $vidColumn,
                'category' => $transaction->category?->name,
            ];
        }
    }
}
