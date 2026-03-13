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
        $today = now()->format('Y-m-d');
        $this->form->fill([
            'rows' => [
                ['date' => $today],
                ['date' => $today],
                ['date' => $today],
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        $cashAccounts = Account::where('type', 'CASH')->orderBy('name')->pluck('name', 'id');
        $categories   = Category::orderBy('name')->pluck('name', 'id');

        return $form
            ->schema([
                Forms\Components\Section::make('Kopīgie lauki')
                    ->description('Konts un kategorija attiecas uz visām rindām zemāk')
                    ->schema([
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
                    ->columns(2),

                Forms\Components\Repeater::make('rows')
                    ->label('Darījumu rindas')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->label('Datums')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false)
                            ->columnSpan(1),

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
                    ->columns(5)
                    ->addActionLabel('+ Pievienot rindu')
                    ->defaultItems(3)
                    ->minItems(1)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string =>
                        (!empty($state['description']) && !empty($state['amount']))
                            ? ($state['date'] ?? '?') . ' · € ' . number_format((float) $state['amount'], 2, ',', ' ') . ' — ' . $state['description']
                            : null
                    ),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Filter out empty rows (date, description or amount missing)
        $rows = array_values(array_filter(
            $data['rows'] ?? [],
            fn ($row) => !empty($row['date']) && !empty($row['description']) && isset($row['amount']) && (float) $row['amount'] > 0
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
                'occurred_at'   => $row['date'],
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

        // Keep account/category, reset rows to 3 empty ones (pre-fill today's date)
        $today = now()->format('Y-m-d');
        $this->form->fill([
            'account_id'  => $data['account_id'],
            'category_id' => $data['category_id'] ?? null,
            'rows'        => [
                ['date' => $today],
                ['date' => $today],
                ['date' => $today],
            ],
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
