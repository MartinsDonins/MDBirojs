<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\CashOrder;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
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

    /** Parsed preview rows from the Excel file (shown before confirming import) */
    public array $previewRows = [];

    /**
     * Called from Alpine.js @paste handler in the Blade template.
     * Receives raw clipboard text, parses each line into a repeater row,
     * and appends them to the existing rows.
     */
    public function processBufferDirect(string $text): void
    {
        $lines = array_values(array_filter(
            preg_split('/\r?\n/', trim($text)),
            fn ($l) => trim($l) !== ''
        ));

        if (empty($lines)) {
            return;
        }

        $today   = now()->format('d.m.Y');
        $newRows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Split by tab (Excel / spreadsheet)
            $cols = explode("\t", $line);

            $partner = '';

            if (count($cols) >= 3) {
                // Full row: [date \t] [partner \t] description \t amount
                if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $cols[0], $m)) {
                    $date   = sprintf('%02d.%02d.%s', (int) $m[1], (int) $m[2], $m[3]);
                    $rest   = array_slice($cols, 1);
                    $amount = $this->parseAmount(array_pop($rest));
                    // If 2+ middle columns: first = partner, rest = description
                    if (count($rest) >= 2) {
                        $partner = trim(array_shift($rest));
                    }
                    $desc = implode(' ', $rest);
                } else {
                    $date   = $today;
                    $amount = $this->parseAmount(array_pop($cols));
                    $desc   = implode(' ', $cols);
                }
            } elseif (count($cols) === 2) {
                // Two columns: could be (date, desc) or (desc, amount)
                $date = $today;
                if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $cols[0], $m)) {
                    $date   = sprintf('%02d.%02d.%s', (int) $m[1], (int) $m[2], $m[3]);
                    $amount = $this->parseAmount($cols[1]);
                    $desc   = ($amount === null) ? $cols[1] : '';
                } else {
                    $amount = $this->parseAmount($cols[1]);
                    $desc   = $cols[0];
                }
            } else {
                // Single value — treat as description only
                $date   = $today;
                $desc   = $line;
                $amount = null;
            }

            if (empty($desc)) {
                continue;
            }

            $newRows[] = [
                'date'        => $date,
                'partner'     => $partner ?? '',
                'description' => $desc,
                'amount'      => $amount !== null ? number_format($amount, 2, '.', '') : '',
            ];
        }

        if (empty($newRows)) {
            Notification::make()
                ->title('Neizdevās atpazīt rindas')
                ->warning()
                ->send();
            return;
        }

        // Rows without a description yet (e.g. amount-only rows) — fill these first
        $noDescKeys = array_values(array_filter(
            array_keys($this->data['rows'] ?? []),
            fn ($k) => empty($this->data['rows'][$k]['description'])
        ));

        foreach ($newRows as $i => $row) {
            if (isset($noDescKeys[$i])) {
                $key = $noDescKeys[$i];
                // Fill description (and partner) into the existing row; keep already-filled fields
                $this->data['rows'][$key]['description'] = $row['description'];
                if (!empty($row['partner'])) {
                    $this->data['rows'][$key]['partner'] = $row['partner'];
                }
                if (empty($this->data['rows'][$key]['date'])) {
                    $this->data['rows'][$key]['date'] = $row['date'];
                }
                if (empty($this->data['rows'][$key]['amount']) && $row['amount'] !== '') {
                    $this->data['rows'][$key]['amount'] = $row['amount'];
                }
            } else {
                // No existing row to fill — create a new one
                $this->data['rows'][(string) Str::orderedUuid()] = $row;
            }
        }

        Notification::make()
            ->title('Ielīmētas ' . count($newRows) . ' rindas')
            ->success()
            ->send();
    }

    public function mount(): void
    {
        $today = now()->format('d.m.Y');
        $this->form->fill([
            'account_id'  => session('qre_account_id'),
            'category_id' => session('qre_category_id'),
            'rows'        => [
                ['date' => $today],
                ['date' => $today],
                ['date' => $today],
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        $cashAccounts = Account::whereIn('type', ['CASH', 'PAYPAL', 'PAYSERA'])->orderBy('name')->pluck('name', 'id');
        $categories   = Category::orderBy('name')->pluck('name', 'id');

        return $form
            ->schema([
                Forms\Components\Section::make('Kopīgie lauki')
                    ->description('Konts un noklusējuma kategorija — rindās var norādīt citu kategoriju')
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->label('Konts')
                            ->options($cashAccounts)
                            ->required()
                            ->searchable()
                            ->placeholder('Izvēlēties kontu...')
                            ->helperText($cashAccounts->isEmpty() ? '⚠ Nav neviena CASH/PayPal/Paysera konta' : null),

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

                        Forms\Components\TextInput::make('partner')
                            ->label('Partneris')
                            ->placeholder('Piem.: Cloud Linux, OVH, ...')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('description')
                            ->label('Apraksts')
                            ->required()
                            ->placeholder('Piem.: Biroja preces, benzīns, ...')
                            ->columnSpan(2),

                        Forms\Components\Select::make('category_id')
                            ->label('Kategorija')
                            ->options($categories)
                            ->searchable()
                            ->nullable()
                            ->placeholder('—')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('amount')
                            ->label('Summa (€)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('€')
                            ->columnSpan(1),
                    ])
                    ->columns(8)
                    ->addActionLabel('+ Pievienot rindu')
                    ->defaultItems(3)
                    ->minItems(1)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string =>
                        (!empty($state['description']) && !empty($state['amount']))
                            ? ($state['date'] ?? '?')
                                . (!empty($state['partner']) ? ' · ' . $state['partner'] : '')
                                . ' · € ' . number_format((float) $state['amount'], 2, ',', ' ')
                                . ' — ' . $state['description']
                            : null
                    ),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('excel_import')
                ->label('Importēt no Excel')
                ->icon('heroicon-o-table-cells')
                ->color('violet')
                ->modalHeading('Importēt darījumus no Excel')
                ->modalWidth('lg')
                ->form([
                    Forms\Components\FileUpload::make('excel_file')
                        ->label('Excel fails (.xlsx / .xls)')
                        ->disk('local')
                        ->directory('excel-imports-tmp')
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(10240)
                        ->required(),

                    Forms\Components\Placeholder::make('template_hint')
                        ->label('')
                        ->content(new HtmlString(
                            '<a href="/admin/excel-template/cash" target="_blank"'
                            . ' class="inline-flex items-center gap-1 text-violet-600 dark:text-violet-400 text-sm hover:underline">'
                            . '📥 Lejupielādēt paraugu (.xlsx)</a><br>'
                            . '<span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">'
                            . 'Kolonnas: <b>Datums · Konts · Tips · Partneris · Apraksts · Summa · Valūta</b><br>'
                            . 'Tips: <b>Saņemts</b> = KII (ieņēmums) &nbsp;·&nbsp; <b>Izsniegts</b> = KIO (izdevums)</span>'
                        )),
                ])
                ->action(function (array $data): void {
                    if (empty($data['excel_file'])) {
                        return;
                    }
                    $path = Storage::disk('local')->path($data['excel_file']);
                    $this->parseExcelFileFromPath($path);
                    Storage::disk('local')->delete($data['excel_file']);
                }),

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

    /**
     * Fill the DATE column of existing rows (top-to-bottom) from pasted text.
     * Directly patches $this->data so other fields (description, amount) are untouched.
     */
    public function processDateBuffer(string $text): void
    {
        $values = array_values(array_filter(
            preg_split('/\r?\n/', trim($text)),
            fn ($l) => trim($l) !== ''
        ));

        if (empty($values)) {
            return;
        }

        // Preserve UUID keys — only overwrite the 'date' field per row
        $keys   = array_keys($this->data['rows'] ?? []);
        $filled = 0;

        foreach ($values as $i => $val) {
            $date = $this->parseDateString(trim($val));
            if ($date === null || !isset($keys[$i])) {
                continue;
            }

            $this->data['rows'][$keys[$i]]['date'] = $date;
            $filled++;
        }

        Notification::make()
            ->title("Aizpildīti {$filled} datumi")
            ->success()
            ->send();
    }

    /**
     * Fill the AMOUNT column of existing rows (top-to-bottom) from pasted text.
     * Directly patches $this->data so other fields (date, description) are untouched.
     */
    public function processAmountBuffer(string $text): void
    {
        $values = array_values(array_filter(
            preg_split('/\r?\n/', trim($text)),
            fn ($l) => trim($l) !== ''
        ));

        if (empty($values)) {
            return;
        }

        // Preserve UUID keys — only overwrite the 'amount' field per row
        $keys   = array_keys($this->data['rows'] ?? []);
        $filled = 0;

        foreach ($values as $i => $val) {
            $amount = $this->parseAmount(trim($val));
            if ($amount === null || !isset($keys[$i])) {
                continue;
            }

            $this->data['rows'][$keys[$i]]['amount'] = number_format($amount, 2, '.', '');
            $filled++;
        }

        Notification::make()
            ->title("Aizpildītas {$filled} summas")
            ->success()
            ->send();
    }

    protected function parseDateString(string $value): ?string
    {
        // dd.mm.yyyy or d.m.yyyy (Latvian format)
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            return sprintf('%02d.%02d.%s', (int) $m[1], (int) $m[2], $m[3]);
        }
        // yyyy-mm-dd (ISO)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return sprintf('%02d.%02d.%s', (int) $m[3], (int) $m[2], $m[1]);
        }

        return null;
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

            // Last column = amount; if 2+ middle columns remain: first = partner, rest = description
            $amountRaw = array_pop($rest);
            $amount    = $this->parseAmount($amountRaw);

            $partner = '';
            if (count($rest) >= 2) {
                $partner = trim(array_shift($rest));
            }
            $description = implode(' ', $rest);

            if ($amount === null || $amount <= 0 || trim($description) === '') {
                continue;
            }

            $rows[] = [
                'date'        => $date,
                'partner'     => $partner,
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

    // ──────────────────────────────────────────────────────────────
    // Excel Import
    // ──────────────────────────────────────────────────────────────

    /** Called from the excel_import header action after file upload */
    private function parseExcelFileFromPath(string $path): void
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            // $formatData=false so dates come as raw serial numbers (we handle them)
            $allRows = $sheet->toArray(null, true, false, false);

            if (empty($allRows)) {
                Notification::make()->title('Fails ir tukšs')->warning()->send();
                return;
            }

            array_shift($allRows); // remove header row

            $accounts = Account::whereIn('type', ['CASH', 'PAYPAL', 'PAYSERA'])
                ->get()
                ->mapWithKeys(fn ($a) => [mb_strtolower(trim($a->name)) => $a]);

            $this->previewRows = [];

            foreach ($allRows as $rowIdx => $row) {
                while (count($row) < 7) {
                    $row[] = null;
                }
                [$dateRaw, $accountName, $typeRaw, $partnerRaw, $descRaw, $amountRaw, $currencyRaw] = $row;

                // Skip completely blank rows
                $allEmpty = array_filter(array_map(fn ($v) => trim((string) $v), $row)) === [];
                if ($allEmpty) {
                    continue;
                }

                $errors = [];

                // --- Date ---
                $date = null;
                if ($dateRaw !== null && $dateRaw !== '') {
                    if (is_numeric($dateRaw) && $dateRaw > 0) {
                        try {
                            $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $dateRaw);
                            $date = $dateObj->format('d.m.Y');
                        } catch (\Exception) {
                            $errors[] = 'Nepareizs datuma formāts';
                        }
                    } else {
                        $date = $this->parseDateString(trim((string) $dateRaw));
                        if (!$date) {
                            $errors[] = "Nepareizs datums: {$dateRaw}";
                        }
                    }
                } else {
                    $errors[] = 'Datums ir obligāts';
                }

                // --- Account ---
                $accountNameStr = trim((string) ($accountName ?? ''));
                $accountKey     = mb_strtolower($accountNameStr);
                $account        = $accounts->get($accountKey);
                if (!$account && $accountKey !== '') {
                    // Partial/case-insensitive match
                    $account = $accounts->first(
                        fn ($a, $k) => str_contains($k, $accountKey) || str_contains($accountKey, $k)
                    );
                }
                if (!$account) {
                    $errors[] = "Konts '{$accountNameStr}' nav atrasts sistēmā";
                }

                // --- Type ---
                $typeNorm = mb_strtolower(trim((string) ($typeRaw ?? '')));
                $typeMap  = [
                    'saņemts'  => 'INCOME',  'sanems'    => 'INCOME',
                    'kii'      => 'INCOME',  'income'    => 'INCOME',
                    'izsniegts' => 'EXPENSE', 'kio'      => 'EXPENSE',
                    'expense'  => 'EXPENSE',
                ];
                $type = $typeMap[$typeNorm] ?? null;
                if (!$type) {
                    $errors[] = "Tips '{$typeRaw}' nav atpazīts — izmanto: Saņemts / Izsniegts";
                }

                // --- Amount ---
                $amount = $this->parseAmount((string) ($amountRaw ?? ''));
                if ($amount === null) {
                    $errors[] = 'Nepareiza summa';
                }

                // --- Description ---
                $desc = trim((string) ($descRaw ?? ''));
                if ($desc === '') {
                    $errors[] = 'Apraksts ir obligāts';
                }

                $this->previewRows[] = [
                    'row_num'     => $rowIdx + 2,
                    'date'        => $date ?? trim((string) ($dateRaw ?? '')),
                    'account_id'  => $account?->id,
                    'account'     => $accountNameStr,
                    'type'        => $type,
                    'type_raw'    => trim((string) ($typeRaw ?? '')),
                    'partner'     => trim((string) ($partnerRaw ?? '')),
                    'description' => $desc,
                    'amount'      => $amount,
                    'currency'    => strtoupper(trim((string) ($currencyRaw ?? 'EUR'))) ?: 'EUR',
                    'errors'      => $errors,
                    'skip'        => !empty($errors),
                ];
            }

            // Drop rows that are blank in all meaningful fields
            $this->previewRows = array_values(array_filter(
                $this->previewRows,
                fn ($r) => !empty($r['description']) || !empty($r['account']) || $r['amount'] !== null
            ));

            if (empty($this->previewRows)) {
                Notification::make()->title('Nav datu rindu')->warning()->send();
                return;
            }

            $valid = count(array_filter($this->previewRows, fn ($r) => empty($r['errors'])));
            $total = count($this->previewRows);
            $invalid = $total - $valid;

            Notification::make()
                ->title("Nolasītas {$total} rindas")
                ->body("{$valid} derīgas" . ($invalid > 0 ? ", {$invalid} ar kļūdām (atzīmētas Izlaist)" : '') . ". Pārskatiet zemāk.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()->title('Kļūda lasot failu')->body($e->getMessage())->danger()->send();
            $this->previewRows = [];
        }
    }

    public function togglePreviewSkip(int $index): void
    {
        if (isset($this->previewRows[$index])) {
            $this->previewRows[$index]['skip'] = !$this->previewRows[$index]['skip'];
        }
    }

    public function cancelExcelImport(): void
    {
        $this->previewRows = [];
    }

    public function confirmExcelImport(): void
    {
        $validRows = array_values(array_filter(
            $this->previewRows,
            fn ($r) => !$r['skip'] && empty($r['errors'])
        ));

        if (empty($validRows)) {
            Notification::make()->title('Nav derīgu rindu importam')->warning()->send();
            return;
        }

        $created     = 0;
        $totalAmount = 0.0;

        DB::transaction(function () use ($validRows, &$created, &$totalAmount): void {
            foreach ($validRows as $row) {
                $isIncome    = $row['type'] === 'INCOME';
                $amountSigned = $isIncome ? $row['amount'] : -$row['amount'];
                $date        = Carbon::createFromFormat('d.m.Y', $row['date']);
                $year        = $date->year;

                $tx = Transaction::create([
                    'account_id'        => $row['account_id'],
                    'occurred_at'       => $date,
                    'amount'            => $amountSigned,
                    'currency'          => $row['currency'],
                    'amount_eur'        => $amountSigned,
                    'exchange_rate'     => 1,
                    'description'       => $row['description'],
                    'counterparty_name' => $row['partner'],
                    'type'              => $row['type'],
                    'status'            => 'COMPLETED',
                ]);

                $cashType = $row['type'];
                CashOrder::create([
                    'transaction_id' => $tx->id,
                    'account_id'     => $row['account_id'],
                    'type'           => $cashType,
                    'number'         => CashOrder::generateNumber($cashType, $year),
                    'date'           => $date,
                    'amount'         => $row['amount'],
                    'currency'       => $row['currency'],
                    'basis'          => $row['description'],
                    'person'         => $row['partner'],
                ]);

                $created++;
                $totalAmount += $row['amount'];
            }
        });

        $this->previewRows = [];

        Notification::make()
            ->title("Importēti {$created} darījumi + kases orderi")
            ->body('Kopā: € ' . number_format($totalAmount, 2, ',', ' '))
            ->success()
            ->send();
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
            // Per-row category overrides global; fall back to global if not set
            $categoryId = !empty($row['category_id']) ? $row['category_id'] : ($data['category_id'] ?? null);

            Transaction::create([
                'account_id'        => $data['account_id'],
                'category_id'       => $categoryId,
                'occurred_at'       => Carbon::createFromFormat('d.m.Y', $row['date']),
                'amount'            => $amount,
                'currency'          => 'EUR',
                'amount_eur'        => $amount,
                'exchange_rate'     => 1,
                'description'       => trim($row['description']),
                'counterparty_name' => trim($row['partner'] ?? ''),
                'type'              => 'EXPENSE',
                'status'            => 'COMPLETED',
            ]);

            $created++;
            $total += abs($amount);
        }

        // Persist account/category in session so next visit pre-fills them
        session([
            'qre_account_id'  => $data['account_id'],
            'qre_category_id' => $data['category_id'] ?? null,
        ]);

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
