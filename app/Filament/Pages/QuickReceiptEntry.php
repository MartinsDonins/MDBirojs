<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class QuickReceiptEntry extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-document-plus';
    protected static ?string $navigationLabel = 'Ātrā čeku ievade';
    protected static ?string $title           = 'Ātrā čeku ievade';
    protected static string  $view            = 'filament.pages.quick-receipt-entry';
    protected static ?int    $navigationSort  = 6;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'date'  => now()->format('Y-m-d'),
            'rows'  => [[], [], []],
        ]);
    }

    public function form(Form $form): Form
    {
        $cashAccounts = Account::where('type', 'CASH')->orderBy('name')->pluck('name', 'id');
        $categories   = Category::orderBy('name')->pluck('name', 'id');

        return $form
            ->schema([
                Forms\Components\Section::make('Čeka pamatdati')
                    ->description('Šie lauki attiecas uz visiem darījumiem zemāk')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->label('Čeka datums')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false),

                        Forms\Components\Select::make('account_id')
                            ->label('Kases konts')
                            ->options($cashAccounts)
                            ->required()
                            ->searchable()
                            ->placeholder('Izvēlēties kasi...')
                            ->helperText($cashAccounts->isEmpty() ? '⚠ Nav neviena CASH tipa konta' : null),

                        Forms\Components\Select::make('category_id')
                            ->label('Kategorija (visiem)')
                            ->options($categories)
                            ->searchable()
                            ->nullable()
                            ->placeholder('Nav (neobligāts)'),
                    ])
                    ->columns(3),

                Forms\Components\Repeater::make('rows')
                    ->label('Darījumu rindas')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Apraksts')
                            ->required()
                            ->placeholder('Piem.: Biroja preces, benzīns, ...')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('amount')
                            ->label('Summa (€)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('€')
                            ->columnSpan(1),
                    ])
                    ->columns(4)
                    ->addActionLabel('+ Pievienot rindu')
                    ->defaultItems(3)
                    ->minItems(1)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string =>
                        (!empty($state['description']) && !empty($state['amount']))
                            ? ('€ ' . number_format((float) $state['amount'], 2, ',', ' ') . ' — ' . $state['description'])
                            : null
                    ),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Filter out empty rows (description or amount missing)
        $rows = array_values(array_filter(
            $data['rows'] ?? [],
            fn ($row) => !empty($row['description']) && isset($row['amount']) && (float) $row['amount'] > 0
        ));

        if (empty($rows)) {
            Notification::make()
                ->title('Nav derīgu ierakstu')
                ->body('Aizpildiet vismaz vienu rindu ar aprakstu un summu.')
                ->warning()
                ->send();
            return;
        }

        $created = 0;
        $total   = 0.0;

        foreach ($rows as $row) {
            $amount = -(float) $row['amount']; // negative = expense

            Transaction::create([
                'account_id'    => $data['account_id'],
                'category_id'   => $data['category_id'] ?? null,
                'occurred_at'   => $data['date'],
                'amount'        => $amount,
                'currency'      => 'EUR',
                'amount_eur'    => $amount,
                'exchange_rate' => 1,
                'description'   => trim($row['description']),
                'type'          => 'EXPENSE',
                'status'        => 'COMPLETED',
            ]);

            $created++;
            $total += abs($amount);
        }

        // Keep date/account/category, reset rows to 3 empty ones
        $this->form->fill([
            'date'        => $data['date'],
            'account_id'  => $data['account_id'],
            'category_id' => $data['category_id'] ?? null,
            'rows'        => [[], [], []],
        ]);

        Notification::make()
            ->title("Izveidoti {$created} darījumi")
            ->body('Kopā: € ' . number_format($total, 2, ',', ' ') . '. Kases orderi izveidoti automātiski.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Saglabāt visus darījumus')
                ->submit('save')
                ->color('primary')
                ->icon('heroicon-o-check-circle')
                ->size('lg'),
        ];
    }
}
