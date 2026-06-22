<?php

namespace App\Filament\Pages;

use App\Services\Gid\GidDeclarationService;
use App\Services\Gid\GidFieldRegistry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

/**
 * Full self-employed annual income declaration (Gada ienākumu deklarācija), shown
 * per year in an expandable accordion. D3 and the tax figures are derived from the
 * journal; the remaining sections (D1, D11, D2, D4, D-summary) are manual or adopted
 * from an imported EDS XML declaration.
 *
 * Each year also has a comparison panel: upload the EDS XML and the page shows, field
 * by field, where the system value and the EDS value disagree — so journal/mapping
 * errors are easy to spot — and lets you adopt the EDS value into the system.
 */
class AnnualDeclaration extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon  = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Gada deklarācija';
    protected static ?string $title           = 'Gada ienākumu deklarācija (saimnieciskā darbība)';
    protected static string $view             = 'filament.pages.annual-declaration';
    protected ?string $maxContentWidth        = 'full';
    protected static ?int $navigationSort     = 9;

    /** @var int[] */
    public array $availableYears = [];
    /** @var int[] currently expanded years */
    public array $expandedYears = [];

    /** Editable manual values: [year => [fieldKey => string]] */
    public array $manual = [];
    /** Computed system values per loaded year: [year => [fieldKey => float]] */
    public array $systemData = [];
    /** Comparison result per loaded year. */
    public array $compareData = [];

    /** Temporary EDS uploads keyed by year (Livewire file model). */
    public array $eds = [];
    /** Path to assign for an unmapped EDS field, keyed by year. */
    public array $assignTarget = [];

    public function mount(): void
    {
        $this->availableYears = $this->service()->availableYears();
        // Expand the most recent year by default.
        if (! empty($this->availableYears)) {
            $this->expandYear($this->availableYears[0]);
        }
    }

    private function service(): GidDeclarationService
    {
        return app(GidDeclarationService::class);
    }

    // ── Accordion ─────────────────────────────────────────────────

    public function toggleYear(int $year): void
    {
        if (in_array($year, $this->expandedYears, true)) {
            $this->expandedYears = array_values(array_diff($this->expandedYears, [$year]));
        } else {
            $this->expandYear($year);
        }
    }

    private function expandYear(int $year): void
    {
        if (! in_array($year, $this->expandedYears, true)) {
            $this->expandedYears[] = $year;
        }
        $this->loadYear($year);
    }

    private function loadYear(int $year): void
    {
        $this->systemData[$year]  = $this->service()->systemValues($year);
        $this->compareData[$year] = $this->service()->compare($year);

        foreach (GidFieldRegistry::manualKeys() as $key) {
            $this->manual[$year][$key] = number_format((float) ($this->systemData[$year][$key] ?? 0), 2, ',', '');
        }
    }

    // ── Section structure for the view ────────────────────────────

    /** @return array<int,array<string,mixed>> */
    public function sections(): array
    {
        return GidFieldRegistry::sections();
    }

    // ── Manual field editing ──────────────────────────────────────

    public function updatedManual(string $value, string $key): void
    {
        // $key = "2025.d1_employment_income"
        [$year, $field] = array_pad(explode('.', $key, 2), 2, null);
        if ($year === null || $field === null) {
            return;
        }
        $year   = (int) $year;
        $amount = $this->parseAmount($value);

        $this->service()->saveManualField($year, $field, $amount);
        $this->manual[$year][$field] = number_format($amount, 2, ',', '');
        $this->loadYear($year);
    }

    // ── EDS import ────────────────────────────────────────────────

    public function updatedEds(mixed $value, string $key): void
    {
        $year = (int) $key;
        $file = $this->eds[$year] ?? null;
        if (! $file) {
            return;
        }

        try {
            $path  = $file->getRealPath();
            $count = count($this->service()->importEds($year, $path, $file->getClientOriginalName()));
            $this->loadYear($year);
            Notification::make()
                ->title("EDS deklarācija ielādēta ({$count} lauki)")
                ->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Neizdevās nolasīt EDS XML')
                ->body($e->getMessage())
                ->danger()->send();
        } finally {
            unset($this->eds[$year]);
        }
    }

    public function adoptField(int $year, string $field): void
    {
        if ($this->service()->adopt($year, $field)) {
            $this->loadYear($year);
            Notification::make()->title('EDS vērtība pārņemta sistēmā')->success()->send();
        } else {
            Notification::make()->title('Nevarēja pārņemt vērtību')->warning()->send();
        }
    }

    public function assignPath(int $year, string $field): void
    {
        $path = $this->assignTarget[$year][$field] ?? null;
        if (! $path) {
            Notification::make()->title('Vispirms izvēlies EDS lauku')->warning()->send();
            return;
        }
        $this->service()->assignEdsPath($year, $field, $path);
        $this->loadYear($year);
        Notification::make()->title('EDS lauks piesaistīts')->success()->send();
    }

    // ── helpers ──────────────────────────────────────────────────

    private function parseAmount(string $value): float
    {
        $val = str_replace([' ', "\xc2\xa0"], '', trim($value));
        if (str_contains($val, ',')) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        }
        return round((float) $val, 2);
    }
}
