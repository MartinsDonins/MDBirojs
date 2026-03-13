<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Actions\Action as HeaderAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class QuickReceiptEntry extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon  = 'heroicon-o-document-plus';
    protected static ?string $navigationLabel = 'Ātrā čeku ievade';
    protected static ?string $title           = 'Ātrā čeku ievade';
    protected static string  $view            = 'filament.pages.quick-receipt-entry';
    protected static ?int    $navigationSort  = 6;

    public ?array $data = [];

    public function mount(): void
    {
        $today = now()->format('d.m.Y');
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
                        Forms\Components\TextInput::make('date')
                            ->label('Datums')
                            ->required()
                            ->mask('99.99.9999')
                            ->placeholder('12.02.2014')
                            ->rules(['regex:/^\d{2}\.\d{2}\.\d{4}$/'])
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

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('paste_rows')
                ->label('Ielīmēt no Excel')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->modalHeading('Ielīmēt rindas no Excel / tabulas')
                ->modalDescription('Kopē rindas no Excel un ielīmē zemāk. Kolonnu secība: Datums (dd.mm.gggg) · Apraksts · Summa. Kolonnas atdala ar Tab. Bez datuma kolonnā — tiek izmantots šodienas datums.')
                ->modalWidth('xl')
                ->form([
                    Forms\Components\Textarea::make('pasted_text')
                        ->label('Ielīmētie dati')
                        ->placeholder("12.03.2026\tBiroja preces\t45,50\n12.03.2026\tBenzīns\t38,00")
                        ->rows(12)
                        ->required()
                        ->helperText('Lai kopētu no Excel: atzīmē rindas → Ctrl+C → ielīmē šeit (Ctrl+V).'),

                    Forms\Components\Radio::make('replace_mode')
                        ->label('Darbība ar esošajām rindām')
                        ->options([
                            'replace' => 'Aizstāt visas rindas',
                            'append'  => 'Pievienot klāt esošajām',
                        ])
                        ->default('replace')
                        ->inline(),
                ])
                ->action(function (array $data): void {
                    $parsed = $this->parsePastedText($data['pasted_text']);

                    if (empty($parsed)) {
                        Notification::make()
                            ->title('Neizdevās nolasīt rindas')
                            ->body('Pārbaudiet, ka dati ir pareizā formātā: apraksts un summa (un pēc izvēles datums).')
                            ->warning()
                            ->send();
                        return;
                    }

                    $current = $this->data;

                    if ($data['replace_mode'] === 'append') {
                        $existing = array_values(array_filter(
                            $current['rows'] ?? [],
                            fn ($r) => !empty($r['description']) || !empty($r['amount'])
                        ));
                        $newRows = array_merge($existing, $parsed);
                    } else {
                        $newRows = $parsed;
                    }

                    $this->form->fill([
                        'account_id'  => $current['account_id'] ?? null,
                        'category_id' => $current['category_id'] ?? null,
                        'rows'        => $newRows,
                    ]);

                    Notification::make()
                        ->title('Ielīmētas ' . count($parsed) . ' rindas')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function parsePastedText(string $text): array
    {
        $rows  = [];
        $today = now()->format('d.m.Y');

        foreach (preg_split('/\r?\n/', trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Split by tab (Excel default); fall back to semicolon
            $cols = explode("\t", $line);
            if (count($cols) < 2) {
                $cols = explode(';', $line);
            }
            $cols = array_values(array_map('trim', $cols));

            if (count($cols) < 2) {
                continue;
            }

            // Detect if first column is a date (dd.mm.yyyy or d.m.yyyy)
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $cols[0], $m)) {
                $date = sprintf('%02d.%02d.%s', (int) $m[1], (int) $m[2], $m[3]);
                $rest = array_slice($cols, 1);
            } else {
                $date = $today;
                $rest = $cols;
            }

            if (count($rest) < 2) {
                continue;
            }

            // Last column treated as amount, everything before as description
            $amountRaw   = array_pop($rest);
            $description = implode(' ', $rest);
            $amount      = $this->parseAmount($amountRaw);

            if ($amount === null || $amount <= 0 || trim($description) === '') {
                continue;
            }

            $rows[] = [
                'date'        => $date,
                'description' => trim($description),
                'amount'      => number_format($amount, 2, '.', ''),
            ];
        }

        return $rows;
    }

    protected function parseAmount(string $value): ?float
    {
        // Strip everything except digits, comma, dot, minus
        $cleaned = preg_replace('/[^0-9,.\-]/', '', trim($value));

        if ($cleaned === '') {
            return null;
        }

        // Both comma and dot present: last one is the decimal separator
        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $lastComma = strrpos($cleaned, ',');
            $lastDot   = strrpos($cleaned, '.');
            if ($lastComma > $lastDot) {
                // European: "1.234,56" → remove dots, comma to dot
                $cleaned = str_replace(['.', ','], ['', '.'], $cleaned);
            } else {
                // US: "1,234.56" → remove commas
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif (str_contains($cleaned, ',')) {
            // Only comma → decimal separator
            $cleaned = str_replace(',', '.', $cleaned);
        }
        // Only dot or plain integer → leave as-is

        $num = (float) $cleaned;

        return $num > 0 ? $num : null;
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
                'occurred_at'   => Carbon::createFromFormat('d.m.Y', $row['date']),
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
        $today = now()->format('d.m.Y');
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
