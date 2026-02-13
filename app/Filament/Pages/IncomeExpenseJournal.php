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

    public ?int $selectedYear = null;
    public ?int $selectedMonth = null; // null = show year summary, 1-12 = show month detail
    
    public array $summary = [
        'total_income' => 0,
        'total_expense' => 0,
        'balance' => 0,
    ];
    
    public array $monthlySummary = [];

    public function mount(): void
    {
        $this->selectedYear = (int) date('Y');
        $this->selectedMonth = null; // Start with year summary
        $this->calculateSummary();
        $this->calculateMonthlySummary();
    }

    public function table(Table $table): Table
    {
        // If month is selected, show detailed transactions
        if ($this->selectedMonth !== null) {
            return $this->getMonthDetailTable($table);
        }
        
        // Otherwise show monthly summary
        return $this->getMonthlySummaryTable($table);
    }

    protected function getMonthlySummaryTable(Table $table): Table
    {
        return $table
            ->query(
                // Dummy query - we'll use custom data
                Transaction::query()->whereRaw('1 = 0')
            )
            ->columns([
                Tables\Columns\TextColumn::make('month')
                    ->label('Mēnesis')
                    ->sortable(false),
                    
                Tables\Columns\TextColumn::make('income')
                    ->label('Ieņēmumi (EUR)')
                    ->money('EUR')
                    ->alignEnd(),
                    
                Tables\Columns\TextColumn::make('expense')
                    ->label('Izdevumi (EUR)')
                    ->money('EUR')
                    ->alignEnd(),
                    
                Tables\Columns\TextColumn::make('balance')
                    ->label('Bilance (EUR)')
                    ->money('EUR')
                    ->alignEnd()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Skatīt')
                    ->icon('heroicon-o-eye')
                    ->action(fn ($record) => $this->viewMonthDetails($record['month_number'])),
            ])
            ->paginated(false)
            ->records(fn () => $this->monthlySummary);
    }

    protected function getMonthDetailTable(Table $table): Table
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

    protected function getMonthDetailQuery(): Builder
    {
        return Transaction::query()
            ->with(['category', 'account'])
            ->where('status', 'COMPLETED')
            ->whereIn('type', ['INCOME', 'EXPENSE'])
            ->whereYear('occurred_at', $this->selectedYear)
            ->whereMonth('occurred_at', $this->selectedMonth);
    }

    protected function calculateMonthlySummary(): void
    {
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
        $runningBalance = 0;

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
        $summary = Transaction::query()
            ->where('status', 'COMPLETED')
            ->whereYear('occurred_at', $this->selectedYear)
            ->selectRaw('
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = ? THEN ABS(amount) ELSE 0 END) as total_expense
            ', ['INCOME', 'EXPENSE'])
            ->first();

        $this->summary = [
            'total_income' => $summary->total_income ?? 0,
            'total_expense' => $summary->total_expense ?? 0,
            'balance' => ($summary->total_income ?? 0) - ($summary->total_expense ?? 0),
        ];
    }

    public function viewMonthDetails(int $month): void
    {
        $this->selectedMonth = $month;
    }

    public function backToYearSummary(): void
    {
        $this->selectedMonth = null;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        // Back button when viewing month details
        if ($this->selectedMonth !== null) {
            $actions[] = \Filament\Actions\Action::make('back')
                ->label('Atpakaļ uz gada kopsavilkumu')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action('backToYearSummary');
        }

        // Export actions (placeholders)
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

        return 'Saimnieciskās darbības ieņēmumu un izdevumu uzskaites žurnāls - ' . $this->selectedYear . '. gads';
    }
}
