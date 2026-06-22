<?php

namespace App\Services\Gid;

/**
 * Declarative structure of the self-employed annual income declaration (GID):
 * the sections shown on the page and compared against an EDS export, and where
 * each field's value comes from.
 *
 * source:
 *   journal  – derived from the income/expense journal (D3) — read-only
 *   tax      – computed tax figures (profit, IIN, VSAOI) — read-only
 *   computed – derived in {@see GidDeclarationService} from other fields — read-only
 *   manual   – user-entered, or adopted from an imported EDS declaration — editable
 *
 * eds_code:
 *   The matching field code/path in the EDS XML export. Left null until a real EDS
 *   sample is available; the comparison still works generically (unmapped EDS fields
 *   are listed separately so they can be assigned). Fill these in once a sample exists.
 */
class GidFieldRegistry
{
    /**
     * @return array<int,array{code:string,title:string,fields:array<int,array<string,mixed>>}>
     */
    public static function sections(): array
    {
        return [
            [
                'code'  => 'D3',
                'title' => 'D3 — Ienākumi no saimnieciskās darbības',
                'fields' => [
                    ['key' => 'd3_income',      'label' => 'Saimnieciskās darbības ieņēmumi (5. rinda)', 'source' => 'journal', 'eds_code' => null],
                    ['key' => 'd3_expenses',    'label' => 'Saimnieciskās darbības izdevumi (6. rinda)',  'source' => 'journal', 'eds_code' => null],
                    ['key' => 'd3_nontaxable',  'label' => 'Neapliekamie ienākumi (4. rinda)',            'source' => 'journal', 'eds_code' => null],
                    ['key' => 'd3_taxable',     'label' => 'Apliekamie ienākumi no saimn. darbības (8. rinda)', 'source' => 'journal', 'eds_code' => null],
                ],
            ],
            [
                'code'  => 'TAX',
                'title' => 'Nodokļu aprēķins (IIN + VSAOI)',
                'fields' => [
                    ['key' => 'tax_profit', 'label' => 'Peļņa (ieņēmumi − izdevumi)',          'source' => 'tax', 'eds_code' => null],
                    ['key' => 'tax_iin',    'label' => 'Aprēķinātais IIN',                      'source' => 'tax', 'eds_code' => null],
                    ['key' => 'tax_vsaoi',  'label' => 'Aprēķinātās VSAOI',                     'source' => 'tax', 'eds_code' => null],
                ],
            ],
            [
                'code'  => 'D1',
                'title' => 'D1 — Ienākumi, par kuriem samaksāts nodoklis (algots darbs)',
                'fields' => [
                    ['key' => 'd1_employment_income', 'label' => 'Algota darba ienākumi (bruto)', 'source' => 'manual', 'eds_code' => null],
                    ['key' => 'd1_tax_withheld',      'label' => 'Ieturētais IIN',                'source' => 'manual', 'eds_code' => null],
                    ['key' => 'd1_vsaoi_withheld',    'label' => 'Ieturētās VSAOI',               'source' => 'manual', 'eds_code' => null],
                ],
            ],
            [
                'code'  => 'D11',
                'title' => 'D11 — Neapliekamie ienākumi',
                'fields' => [
                    ['key' => 'd11_nontaxable', 'label' => 'Neapliekamie ienākumi (kopā)', 'source' => 'manual', 'eds_code' => null],
                ],
            ],
            [
                'code'  => 'D2',
                'title' => 'D2 — Ienākumi ārvalstīs',
                'fields' => [
                    ['key' => 'd2_foreign_income', 'label' => 'Ārvalstīs gūtie ienākumi', 'source' => 'manual', 'eds_code' => null],
                    ['key' => 'd2_foreign_tax',    'label' => 'Ārvalstīs samaksātais nodoklis', 'source' => 'manual', 'eds_code' => null],
                ],
            ],
            [
                'code'  => 'D4',
                'title' => 'D4 — Attaisnotie izdevumi un atvieglojumi',
                'fields' => [
                    ['key' => 'd4_education_medical', 'label' => 'Izglītība un ārstniecība',        'source' => 'manual', 'eds_code' => null],
                    ['key' => 'd4_donations',         'label' => 'Ziedojumi un dāvinājumi',         'source' => 'manual', 'eds_code' => null],
                    ['key' => 'd4_dependent_relief',  'label' => 'Atvieglojumi par apgādājamiem',   'source' => 'manual', 'eds_code' => null],
                ],
            ],
            [
                'code'  => 'D',
                'title' => 'D — Pamatdeklarācija (kopsavilkums)',
                'fields' => [
                    ['key' => 'd_taxable_total',     'label' => 'Apliekamie ienākumi kopā',                 'source' => 'computed', 'eds_code' => null],
                    ['key' => 'd_nontaxable_minimum','label' => 'Gada neapliekamais minimums',              'source' => 'manual',   'eds_code' => null],
                    ['key' => 'd_deductible_total',  'label' => 'Attaisnotie izdevumi + atvieglojumi kopā', 'source' => 'computed', 'eds_code' => null],
                    ['key' => 'd_tax_base',          'label' => 'Ar nodokli apliekamā bāze',                'source' => 'computed', 'eds_code' => null],
                    ['key' => 'd_calculated_tax',    'label' => 'Aprēķinātais nodoklis (kopā)',             'source' => 'computed', 'eds_code' => null],
                    ['key' => 'd_paid_tax',          'label' => 'Samaksātais / ieturētais nodoklis',        'source' => 'computed', 'eds_code' => null],
                    ['key' => 'd_balance',           'label' => 'Piemaksa (+) / atmaksa (−)',               'source' => 'computed', 'eds_code' => null],
                ],
            ],
        ];
    }

    /**
     * Flat map of field key → definition (label, source, section code, eds_code).
     *
     * @return array<string,array<string,mixed>>
     */
    public static function flat(): array
    {
        $out = [];
        foreach (self::sections() as $section) {
            foreach ($section['fields'] as $f) {
                $out[$f['key']] = $f + ['section' => $section['code']];
            }
        }
        return $out;
    }

    /** Keys whose values are user-editable (manual / EDS-adopted). */
    public static function manualKeys(): array
    {
        return array_keys(array_filter(self::flat(), fn ($f) => $f['source'] === 'manual'));
    }

    /** field key => eds_code, only for mapped fields. */
    public static function edsCodeMap(): array
    {
        $out = [];
        foreach (self::flat() as $key => $f) {
            if (! empty($f['eds_code'])) {
                $out[$key] = $f['eds_code'];
            }
        }
        return $out;
    }
}
