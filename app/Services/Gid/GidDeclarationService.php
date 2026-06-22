<?php

namespace App\Services\Gid;

use App\Models\GidDeclaration;
use App\Models\JournalColumn;
use App\Models\ProfitLossSetting;
use App\Models\Transaction;
use App\Services\D3DeclarationService;
use Illuminate\Support\Facades\DB;

/**
 * Builds the full self-employed annual income declaration (GID) for a year and
 * compares it against an imported EDS XML declaration.
 *
 * System values come from three places:
 *   - journal  : the D3 income/expense figures ({@see D3DeclarationService})
 *   - tax      : IIN + VSAOI, computed here (same formula as the "Nodokļu aprēķins" page)
 *   - manual   : user-entered or EDS-adopted values stored on {@see GidDeclaration}
 * and the D-summary fields are derived from those.
 */
class GidDeclarationService
{
    /** Per-year tax defaults (IIN %, min monthly wage, VSAOI full %, VSAOI reduced %). */
    private const YEAR_DEFAULTS = [
        2018 => ['iin' => 20.0, 'wage' => 430.0, 'full' => 32.15, 'reduced' => 5.0],
        2019 => ['iin' => 20.0, 'wage' => 430.0, 'full' => 32.15, 'reduced' => 5.0],
        2020 => ['iin' => 20.0, 'wage' => 430.0, 'full' => 32.15, 'reduced' => 5.0],
        2021 => ['iin' => 20.0, 'wage' => 500.0, 'full' => 31.07, 'reduced' => 10.0],
        2022 => ['iin' => 20.0, 'wage' => 500.0, 'full' => 31.07, 'reduced' => 10.0],
        2023 => ['iin' => 20.0, 'wage' => 620.0, 'full' => 31.07, 'reduced' => 10.0],
        2024 => ['iin' => 20.0, 'wage' => 700.0, 'full' => 31.07, 'reduced' => 10.0],
        2025 => ['iin' => 25.5, 'wage' => 740.0, 'full' => 31.07, 'reduced' => 10.0],
        2026 => ['iin' => 25.5, 'wage' => 780.0, 'full' => 31.07, 'reduced' => 10.0],
    ];

    private const GENERIC_DEFAULTS = ['iin' => 25.5, 'wage' => 780.0, 'full' => 31.07, 'reduced' => 10.0];

    public function __construct(private readonly D3DeclarationService $d3)
    {
    }

    /**
     * Years that have journal data, descending.
     *
     * @return int[]
     */
    public function availableYears(): array
    {
        return $this->d3->availableYears();
    }

    /**
     * All GID field values for a year, keyed by field key.
     *
     * @return array<string,float>
     */
    public function systemValues(int $year): array
    {
        $d3   = $this->d3->fullReport($year)['rows'];
        $tax  = $this->taxValues($year, $d3['row5'], $d3['row6']);
        $rec  = GidDeclaration::firstWhere('year', $year);
        $data = $rec?->data ?? [];

        $v = [
            // D3 (journal)
            'd3_income'     => $d3['row5'],
            'd3_expenses'   => $d3['row6'],
            'd3_nontaxable' => $d3['row4'],
            'd3_taxable'    => $d3['row8'],
            // Tax
            'tax_profit'    => $tax['profit'],
            'tax_iin'       => $tax['iin'],
            'tax_vsaoi'     => $tax['vsaoi'],
        ];

        // Manual / EDS-adopted fields
        foreach (GidFieldRegistry::manualKeys() as $key) {
            $v[$key] = (float) ($data[$key] ?? 0);
        }

        // D-summary (computed)
        $v['d_taxable_total']      = $v['d3_taxable'] + $v['d1_employment_income'] + $v['d2_foreign_income'];
        $v['d_deductible_total']   = $v['d4_education_medical'] + $v['d4_donations'] + $v['d4_dependent_relief'];
        $v['d_tax_base']           = max(0.0, $v['d_taxable_total'] - $v['d_nontaxable_minimum'] - $v['d_deductible_total']);
        $v['d_calculated_tax']     = $v['tax_iin']; // IIN on business income; employment IIN already withheld
        $v['d_paid_tax']           = $v['d1_tax_withheld'] + $v['d2_foreign_tax'];
        $v['d_balance']            = $v['d_calculated_tax'] - $v['d_paid_tax'];

        return array_map(fn ($n) => round((float) $n, 2), $v);
    }

    /**
     * Compute IIN + VSAOI for the year from journal profit.
     *
     * @return array{profit:float,iin:float,vsaoi:float}
     */
    private function taxValues(int $year, float $income, float $expenses): array
    {
        $profit = $income - $expenses;

        $d       = self::YEAR_DEFAULTS[$year] ?? self::GENERIC_DEFAULTS;
        $setting = ProfitLossSetting::firstWhere('year', $year);

        $iinRate = (float) ($setting->tax_rate          ?? $d['iin']);
        $minWage = (float) ($setting->min_wage          ?? $d['wage']);
        $full    = (float) ($setting->vsaa_full_rate    ?? $d['full']);
        $reduced = (float) ($setting->vsaa_reduced_rate ?? $d['reduced']);

        $iin = $profit > 0 ? round($profit * $iinRate / 100, 2) : 0.0;

        $vsaoi = 0.0;
        foreach ($this->monthlyProfits($year) as $mProfit) {
            if ($mProfit <= 0) {
                continue;
            }
            $vsaoi += $mProfit < $minWage
                ? round($mProfit * $reduced / 100, 2)
                : round($minWage * $full / 100 + ($mProfit - $minWage) * $reduced / 100, 2);
        }

        return ['profit' => $profit, 'iin' => $iin, 'vsaoi' => round($vsaoi, 2)];
    }

    /**
     * Monthly taxable profit (taxable income − deductible expenses) for the year.
     *
     * @return array<int,float> month (1–12) => profit
     */
    private function monthlyProfits(int $year): array
    {
        $incomeCol  = JournalColumn::visibleForGroup('income')->first();
        $expenseCol = JournalColumn::visibleForGroup('expense')->first();
        $incomeVids = array_map('intval', $incomeCol->vid_columns ?? []);
        $expVids    = array_map('intval', $expenseCol->vid_columns ?? []);

        $rows = Transaction::query()
            ->where('transactions.status', 'COMPLETED')
            ->whereIn('transactions.type', ['INCOME', 'EXPENSE', 'FEE'])
            ->whereYear('transactions.occurred_at', $year)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw('EXTRACT(MONTH FROM transactions.occurred_at)::int AS mo, transactions.type, categories.vid_column AS vid,
                         SUM(ABS(COALESCE(transactions.amount_eur, transactions.amount))) AS total')
            ->groupBy(DB::raw('EXTRACT(MONTH FROM transactions.occurred_at)'), 'transactions.type', 'categories.vid_column')
            ->get();

        $profits = array_fill(1, 12, 0.0);
        foreach ($rows as $r) {
            $vid = (int) $r->vid;
            if ($r->type === 'INCOME' && in_array($vid, $incomeVids, true)) {
                $profits[$r->mo] += (float) $r->total;
            } elseif (in_array($r->type, ['EXPENSE', 'FEE'], true) && in_array($vid, $expVids, true)) {
                $profits[$r->mo] -= (float) $r->total;
            }
        }
        return $profits;
    }

    // ── Manual field editing ──────────────────────────────────────

    public function saveManualField(int $year, string $key, float $value): void
    {
        if (! in_array($key, GidFieldRegistry::manualKeys(), true)) {
            return;
        }
        $rec = GidDeclaration::firstOrNew(['year' => $year]);
        $data = $rec->data ?? [];
        $data[$key] = round($value, 2);
        $rec->data = $data;
        $rec->save();
    }

    // ── EDS XML import + comparison ───────────────────────────────

    /**
     * Parse an EDS declaration XML into a flat map of {path => value}, then store it
     * for the year. Returns the flattened map.
     *
     * @return array<string,string>
     */
    public function importEds(int $year, string $absolutePath, string $filename): array
    {
        $flat = self::withComputedTotals(self::parseEdsXml($absolutePath));

        $rec = GidDeclaration::firstOrNew(['year' => $year]);
        $rec->eds_data = $flat;
        $rec->eds_meta = ['filename' => $filename, 'imported_at' => now()->toDateTimeString()];
        $rec->save();

        // Auto-map the declaration fields to their EDS values so the comparison works
        // out of the box; whatever can't be resolved stays manually assignable.
        $this->applyAutoMap($year, $flat);

        return $flat;
    }

    /**
     * Add synthetic "D1 total" entries to the flattened EDS data. The EDS export lists
     * D1 income as one row per payer (PielikumsD1/R[n]/…); the declaration needs the
     * per-column totals, so we sum them by field name (BrutoIenem / NodAvanss / Vsaoi),
     * which are stable across every schema version (DokIINGDv2…v11).
     *
     * @param  array<string,string>  $flat
     * @return array<string,string>
     */
    public static function withComputedTotals(array $flat): array
    {
        $totals = [
            'BrutoIenem' => 'D1KOPA/BrutoIenem',
            'NodAvanss'  => 'D1KOPA/NodAvanss',
            'Vsaoi'      => 'D1KOPA/Vsaoi',
        ];

        foreach ($totals as $field => $key) {
            $re = '~/PielikumsD1/R(\[\d+\])?/'.preg_quote($field, '~').'$~';
            $found = false;
            $sum = 0.0;
            foreach ($flat as $path => $val) {
                if (preg_match($re, $path)) {
                    $found = true;
                    $sum += self::toFloat($val);
                }
            }
            if ($found) {
                $flat[$key] = number_format(round($sum, 2), 2, '.', '');
            }
        }

        return $flat;
    }

    /**
     * Resolve the EDS XML paths for the declaration fields from flattened EDS data. The
     * GID schema version (DokIINGDv2…v11) and many field codes differ every year, so the
     * paths are resolved from the actual data rather than a static table:
     *   - D3 income   : PielikumsD3/A09 (2014–2017) or /A11 (2018+)
     *   - D3 expenses : PielikumsD3/A10 (2014–2017) or /A121 | /A122 (2018+, by method)
     *   - D1 totals   : the synthetic per-column sums from {@see withComputedTotals()}
     *   - D4 / D minimum: SadalaD line codes, only for the old schema (DokIINGDv2…v4),
     *     where the SadalaD line numbering still matches the printed form.
     * When several candidate codes are present the one carrying a non-zero value wins.
     *
     * @param  array<string,string>  $flat
     * @return array<string,string> field key => eds path
     */
    public static function resolveAutoMap(array $flat): array
    {
        $pick = static function (array $codes, string $section) use ($flat): ?string {
            $firstPresent = null;
            foreach ($codes as $code) {
                foreach ($flat as $path => $val) {
                    if (preg_match('~/'.$section.'/'.preg_quote($code, '~').'$~', $path)) {
                        $firstPresent ??= $path;
                        if (abs(self::toFloat($val)) > 0.0) {
                            return $path; // prefer a code that actually carries a value
                        }
                    }
                }
            }
            return $firstPresent;
        };
        $exact = static fn (string $key): ?string => array_key_exists($key, $flat) ? $key : null;

        $map = [];

        // D3 — saimnieciskā darbība (journal-derived; all years)
        if ($p = $pick(['A09', 'A11'], 'PielikumsD3'))          { $map['d3_income'] = $p; }
        if ($p = $pick(['A10', 'A121', 'A122'], 'PielikumsD3')) { $map['d3_expenses'] = $p; }

        // D1 — algota darba / citi ienākumi (per-column totals; all years)
        if ($p = $exact('D1KOPA/BrutoIenem')) { $map['d1_employment_income'] = $p; }
        if ($p = $exact('D1KOPA/NodAvanss'))  { $map['d1_tax_withheld'] = $p; }
        if ($p = $exact('D1KOPA/Vsaoi'))      { $map['d1_vsaoi_withheld'] = $p; }

        // D4 / D-summary — only the old schema keeps the SadalaD line numbering used here
        if (self::schemaVersion($flat) <= 4) {
            if ($p = $pick(['D08'], 'SadalaD'))        { $map['d4_donations'] = $p; }
            if ($p = $pick(['D13'], 'SadalaD'))        { $map['d4_dependent_relief'] = $p; }
            if ($p = $pick(['D12', 'D11'], 'SadalaD')) { $map['d_nontaxable_minimum'] = $p; }
        }

        return $map;
    }

    /** GID schema version (the v-number of DokIINGDv*), or PHP_INT_MAX when unknown. */
    private static function schemaVersion(array $flat): int
    {
        foreach ($flat as $path => $val) {
            if (preg_match('~/Declaration/DokIINGDv(\d+)/~', $path, $m)) {
                return (int) $m[1];
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * Persist the auto-resolved D3 mapping into the per-year EDS map (merged with any
     * existing user assignments; existing keys are kept).
     *
     * @param  array<string,string>  $flat
     * @return array<string,string> the field=>path entries that were applied
     */
    public function applyAutoMap(int $year, array $flat): array
    {
        $map = self::resolveAutoMap($flat);
        if (empty($map)) {
            return [];
        }

        $rec  = GidDeclaration::firstOrNew(['year' => $year]);
        $data = $rec->data ?? [];
        // User-assigned paths win over the auto-resolved ones.
        $data['_eds_map'] = array_merge($map, $data['_eds_map'] ?? []);
        $rec->data = $data;
        $rec->save();

        return $map;
    }

    /**
     * Flatten an XML file into {path => scalar value}. Repeated siblings get an index
     * suffix (e.g. "Dekl/Rinda[1]"). Robust to any EDS schema — mapping to declaration
     * fields happens separately via {@see effectiveEdsMap()}.
     *
     * @return array<string,string>
     */
    public static function parseEdsXml(string $absolutePath): array
    {
        $xml = @simplexml_load_file($absolutePath);
        if ($xml === false) {
            return [];
        }

        $flat = [];
        self::flatten($xml, $xml->getName(), $flat);
        return $flat;
    }

    /**
     * @param  string  $path  full path of $node (including its own name)
     * @param  array<string,string>  $out
     */
    private static function flatten(\SimpleXMLElement $node, string $path, array &$out): void
    {
        // Attributes
        foreach ($node->attributes() as $aName => $aVal) {
            $out["{$path}@{$aName}"] = trim((string) $aVal);
        }

        $children = $node->children();
        if (count($children) === 0) {
            $text = trim((string) $node);
            if ($text !== '') {
                $out[$path] = $text;
            }
            return;
        }

        // Count names to know which need an index suffix
        $nameCounts = [];
        foreach ($children as $child) {
            $nameCounts[$child->getName()] = ($nameCounts[$child->getName()] ?? 0) + 1;
        }
        $seen = [];
        foreach ($children as $child) {
            $name = $child->getName();
            $base = "{$path}/{$name}";
            if (($nameCounts[$name] ?? 0) > 1) {
                $idx  = ($seen[$name] = ($seen[$name] ?? 0) + 1);
                $base .= "[{$idx}]";
            }
            self::flatten($child, $base, $out);
        }
    }

    /**
     * Effective EDS field mapping = registry codes + per-year user-assigned map.
     *
     * @return array<string,string> field key => eds path
     */
    public function effectiveEdsMap(int $year): array
    {
        $rec  = GidDeclaration::firstWhere('year', $year);
        $user = $rec?->data['_eds_map'] ?? [];
        return array_merge(GidFieldRegistry::edsCodeMap(), $user);
    }

    /** Assign an EDS XML path to a declaration field (saved per year). */
    public function assignEdsPath(int $year, string $fieldKey, string $edsPath): void
    {
        $rec  = GidDeclaration::firstOrNew(['year' => $year]);
        $data = $rec->data ?? [];
        $map  = $data['_eds_map'] ?? [];
        $map[$fieldKey] = $edsPath;
        $data['_eds_map'] = $map;
        $rec->data = $data;
        $rec->save();
    }

    /**
     * Compare system values against the imported EDS declaration.
     *
     * @return array{
     *   rows: array<int,array<string,mixed>>,
     *   unmapped: array<string,string>,
     *   summary: array{match:int,mismatch:int,eds_only:int,no_eds:int},
     *   has_eds: bool,
     *   eds_meta: array<string,mixed>|null
     * }
     */
    public function compare(int $year): array
    {
        $system  = $this->systemValues($year);
        $rec     = GidDeclaration::firstWhere('year', $year);
        $edsData = $rec?->eds_data ?? [];
        $map     = $this->effectiveEdsMap($year);
        $flat    = GidFieldRegistry::flat();

        $rows    = [];
        $summary = ['match' => 0, 'mismatch' => 0, 'eds_only' => 0, 'no_eds' => 0];
        $usedPaths = [];

        foreach ($flat as $key => $def) {
            $sysVal  = $system[$key] ?? 0.0;
            $edsPath = $map[$key] ?? null;
            $hasEds  = $edsPath !== null && array_key_exists($edsPath, $edsData);
            $edsVal  = $hasEds ? self::toFloat($edsData[$edsPath]) : null;

            if ($hasEds) {
                $usedPaths[$edsPath] = true;
            }

            $status = 'no_eds';
            if ($hasEds) {
                $status = abs($sysVal - $edsVal) < 0.01 ? 'match' : 'mismatch';
            }
            $summary[$status]++;

            $rows[] = [
                'key'        => $key,
                'label'      => $def['label'],
                'section'    => $def['section'],
                'source'     => $def['source'],
                'system'     => $sysVal,
                'eds'        => $edsVal,
                'eds_path'   => $edsPath,
                'status'     => $status,
                'adoptable'  => $def['source'] === 'manual' && $status === 'mismatch',
            ];
        }

        // EDS paths that aren't mapped to any field → candidates for assignment
        $unmapped = [];
        foreach ($edsData as $path => $val) {
            if (! isset($usedPaths[$path]) && self::looksNumeric($val)) {
                $unmapped[$path] = $val;
            }
        }

        return [
            'rows'     => $rows,
            'unmapped' => $unmapped,
            'summary'  => $summary,
            'has_eds'  => ! empty($edsData),
            'eds_meta' => $rec?->eds_meta,
        ];
    }

    /** Adopt the EDS value into the system for a manual field. */
    public function adopt(int $year, string $fieldKey): bool
    {
        if (! in_array($fieldKey, GidFieldRegistry::manualKeys(), true)) {
            return false;
        }
        $rec     = GidDeclaration::firstWhere('year', $year);
        $edsPath = $this->effectiveEdsMap($year)[$fieldKey] ?? null;
        if (! $rec || ! $edsPath || ! array_key_exists($edsPath, $rec->eds_data ?? [])) {
            return false;
        }
        $this->saveManualField($year, $fieldKey, self::toFloat($rec->eds_data[$edsPath]));
        return true;
    }

    // ── helpers ──────────────────────────────────────────────────

    private static function toFloat(string $val): float
    {
        $v = str_replace([' ', "\xc2\xa0"], '', trim($val));
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace('.', '', $v);          // 1.234,56
            $v = str_replace(',', '.', $v);
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);          // 1234,56
        }
        return round((float) $v, 2);
    }

    private static function looksNumeric(string $val): bool
    {
        $v = str_replace([' ', "\xc2\xa0", ',', '.'], ['', '', '', ''], trim($val));
        return $v !== '' && ctype_digit(ltrim($v, '-'));
    }
}
