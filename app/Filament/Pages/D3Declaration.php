<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\D3Setting;
use App\Services\D3DeclarationService;
use Filament\Pages\Page;

/**
 * Pre-filled preview of the VID D3 annex ("Ienākumi no saimnieciskās darbības").
 *
 * The taxable income (row 5), deductible expenses (row 6) and non-taxable income
 * (row 4) are read live from the income/expense journal; the farming rows, prior-year
 * losses, foreign tax and minimum taxable income are manual inputs saved per year.
 * Rows 1, 8 and 11 are computed. The whole thing can be downloaded as a PDF.
 *
 * This is a working aid, NOT an official submission — figures must be verified in EDS.
 */
class D3Declaration extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'D3 Gada deklarācija';
    protected static ?string $title           = 'VID D3 pielikums — Ienākumi no saimnieciskās darbības';
    protected static string $view             = 'filament.pages.d3-declaration';
    protected ?string $maxContentWidth        = 'full';
    protected static ?string $navigationGroup = 'VID un deklarācijas';
    protected static ?int $navigationSort     = 2;

    /** Selected taxation year. */
    public ?int $year = null;

    /** Years that have journal data (descending). */
    public array $availableYears = [];

    /**
     * Manual D3 inputs (European-format strings for wire:model), keyed:
     *   farm_1_1, farm_1_2, farm_1_3, farm_1_4, farm_2, farm_3, other_7, foreign_9, min_10
     */
    public array $manual = [];

    /** Taxpayer header (saved globally in app_settings). */
    public string $taxpayerName = '';
    public string $taxpayerCode = '';

    // ── Journal-derived figures for the selected year (read-only) ──
    public float $autoOtherIncome     = 0.0; // row 5
    public float $autoOtherExpenses   = 0.0; // row 6
    public float $autoNonTaxable      = 0.0; // row 4
    public string $incomeAbbr         = '';
    public string $expenseAbbr        = '';
    public string $nonTaxableAbbr     = '';

    private const MANUAL_KEYS = [
        'farm_1_1', 'farm_1_2', 'farm_1_3', 'farm_1_4',
        'farm_2', 'farm_3', 'other_7', 'foreign_9', 'min_10',
    ];

    /** Maps manual array keys → D3Setting column names. */
    private const KEY_TO_COLUMN = [
        'farm_1_1'  => 'farm_income_agriculture',
        'farm_1_2'  => 'farm_income_fishery',
        'farm_1_3'  => 'farm_income_tourism',
        'farm_1_4'  => 'farm_income_support',
        'farm_2'    => 'farm_expenses',
        'farm_3'    => 'farm_prior_losses',
        'other_7'   => 'other_prior_losses',
        'foreign_9' => 'foreign_tax',
        'min_10'    => 'min_taxable_income',
    ];

    public function mount(): void
    {
        $this->availableYears = app(D3DeclarationService::class)->availableYears();
        $this->year = $this->availableYears[0] ?? (int) now()->year;

        $this->taxpayerName = AppSetting::getRaw('taxpayer_name');
        $this->taxpayerCode = AppSetting::getRaw('taxpayer_code');

        $this->loadYear();
    }

    public function updatedYear(): void
    {
        $this->year = (int) $this->year;
        $this->loadYear();
    }

    public function updatedManual(string $value, string $key): void
    {
        if (! in_array($key, self::MANUAL_KEYS, true)) {
            return;
        }

        $amount  = $this->parseAmount($value);
        $column  = self::KEY_TO_COLUMN[$key];

        D3Setting::updateOrCreate(['year' => $this->year], [$column => $amount]);
        $this->manual[$key] = $this->formatInput($amount);
    }

    public function updatedTaxpayerName(): void
    {
        AppSetting::set('taxpayer_name', $this->taxpayerName);
    }

    public function updatedTaxpayerCode(): void
    {
        AppSetting::set('taxpayer_code', $this->taxpayerCode);
    }

    // ──────────────────────────────────────────────────────────────

    private function loadYear(): void
    {
        $auto = app(D3DeclarationService::class)->build($this->year);

        $this->autoOtherIncome   = $auto['other_income'];
        $this->autoOtherExpenses = $auto['other_expenses'];
        $this->autoNonTaxable    = $auto['non_taxable_income'];
        $this->incomeAbbr        = $auto['income_abbr'];
        $this->expenseAbbr       = $auto['expense_abbr'];
        $this->nonTaxableAbbr    = $auto['non_taxable_abbr'];

        $s = D3Setting::firstOrNew(['year' => $this->year]);
        foreach (self::KEY_TO_COLUMN as $key => $column) {
            $this->manual[$key] = $this->formatInput((float) ($s->{$column} ?? 0));
        }
    }

    /**
     * The full D3 row set for the view (and the PDF), with computed totals.
     *
     * @return array<string,float>
     */
    public function rows(): array
    {
        $manual = [];
        foreach (self::MANUAL_KEYS as $k) {
            $manual[$k] = $this->parseAmount($this->manual[$k] ?? '0');
        }

        return D3DeclarationService::computeRows([
            'other_income'       => $this->autoOtherIncome,
            'other_expenses'     => $this->autoOtherExpenses,
            'non_taxable_income' => $this->autoNonTaxable,
        ], $manual);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /** Parse "1 234,56" / "1234.56" → float. */
    private function parseAmount(string $value): float
    {
        $val = str_replace([' ', "\xc2\xa0"], '', trim($value));
        if (str_contains($val, ',')) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        }
        return round(max(0.0, (float) $val), 2);
    }

    /** Format a float as an editable European string ("1234.56" → "1234,56"). */
    private function formatInput(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
