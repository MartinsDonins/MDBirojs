<?php

namespace App\Services\Gid;

use Illuminate\Support\Str;

/**
 * Human-readable labels for EDS GID XML field codes, taken from the official VID
 * declaration form (see BRAIN/GID-2014.html). The EDS XML stores cryptic codes
 * (PielikumsD3/A09, SadalaD/D12, PielikumsD1/R/BrutoIenem …); this maps each to the
 * line number + name shown on the printed declaration, so the comparison page can
 * label the EDS values and the "assign" picker instead of showing raw XML paths.
 *
 * Keyed by the trailing "<Section>/<Code>" of the flattened path (repeated-sibling
 * indexes like "[1]" are stripped before matching), longest match wins.
 */
class GidEdsLabels
{
    /**
     * @return array<string,string> path suffix => label
     */
    public static function map(): array
    {
        return [
            // ── D — Gada ienākumu deklarācija (kopsavilkums) → SadalaD ──
            'SadalaD/D05'  => 'D 05. VSAOI (attaisnotie izdevumi)',
            'SadalaD/D08'  => 'D 08. Ziedojumi un dāvinājumi',
            'SadalaD/D091' => 'D 09.1 Iemaksas privātajos pensiju fondos',
            'SadalaD/D092' => 'D 09.2 Dzīvības apdrošināšanas prēmijas',
            'SadalaD/D093' => 'D 09.3 Ieguldījumu fondu apliecību iegāde',
            'SadalaD/D10'  => 'D 10. Attaisnotie izdevumi kopā',
            'SadalaD/D11'  => 'D 11. Gada neapliekamais minimums',
            'SadalaD/D12'  => 'D 12. Gada neapliekamais minimums pensionāram',
            'SadalaD/D13'  => 'D 13. Atvieglojumi par apgādājamiem',
            'SadalaD/D14'  => 'D 14. Atvieglojums par invaliditāti',
            'SadalaD/D15'  => 'D 15. Atvieglojums politiski represētajiem',
            'SadalaD/D16'  => 'D 16. Atvieglojums nac. pretošanās dalībniekiem',
            'SadalaD/D20'  => 'D 20. Avansā samaksātais (ieturētais) nodoklis',
            'SadalaD/D21'  => 'D 21. Piemaksa / pārrēķina rezultāts',

            // ── D1 — Latvijā gūtie ienākumi (ne saimnieciskā darbība) → PielikumsD1/R ──
            'PielikumsD1/R/BrutoIenem'  => 'D1 2. Bruto ieņēmumi',
            'PielikumsD1/R/NeaplIenak'  => 'D1 3. Neapliekamie ienākumi',
            'PielikumsD1/R/Vsaoi'       => 'D1 4a. Darba ņēmēja VSAOI',
            'PielikumsD1/R/PpfUdza'     => 'D1 4b. Pensiju fondu iemaksas / dzīvības apdr.',
            'PielikumsD1/R/AutorIzdev'  => 'D1 4c. Autoru izdevumi',
            'PielikumsD1/R/CitiIzdev'   => 'D1 5. Izdevumi, kas saistīti ar ienākumu gūšanu',
            'PielikumsD1/R/NodAvanss'   => 'D1 7. Avansā samaksātais (ieturētais) nodoklis',

            // ── D3 — Ienākumi no saimnieciskās darbības → PielikumsD3 ──
            'PielikumsD3/A011' => 'D3 01.1 Ieņēmumi no lauksaimnieciskās ražošanas',
            'PielikumsD3/A012' => 'D3 01.2 Ieņēmumi no iekšējo ūdeņu zivsaimniecības',
            'PielikumsD3/A013' => 'D3 01.3 Ieņēmumi no lauku tūrisma pakalpojumiem',
            'PielikumsD3/A014' => 'D3 01.4 Ieņēmumi no atbalsta lauksaimniecībai',
            'PielikumsD3/A02'  => 'D3 02. Izdevumi (lauksaimniecība / lauku tūrisms)',
            'PielikumsD3/A021' => 'D3 02. Izdevumi (lauksaimniecība / lauku tūrisms)',
            'PielikumsD3/A022' => 'D3 02. Izdevumi (lauksaimniecība / lauku tūrisms)',
            'PielikumsD3/A04'  => 'D3 04. Neapliekamie ienākumi (lauksaimniecība)',
            'PielikumsD3/A07'  => 'D3 07. Apliekamie ienākumi (lauksaimniecība), ņemot vērā zaudējumus',
            'PielikumsD3/A08'  => 'D3 08. Neapliekamie ienākumi',
            'PielikumsD3/A09'  => 'D3 09. Ieņēmumi no citiem saimnieciskās darbības veidiem',
            'PielikumsD3/A10'  => 'D3 10. Izdevumi, kas saistīti ar citiem saimn. darbības veidiem',
            'PielikumsD3/A11'  => 'D3 11. Ieņēmumi no saimnieciskās darbības',
            'PielikumsD3/A121' => 'D3 12. Saimnieciskās darbības izdevumi',
            'PielikumsD3/A122' => 'D3 12. Saimnieciskās darbības izdevumi (pēc normas)',
            'PielikumsD3/A14a' => 'D3 14a. Par pašu maksātāju veiktās VSAOI',
            'PielikumsD3/A16'  => 'D3 16. Avansā samaksātais nodoklis',
            'PielikumsD3/A17'  => 'D3 17. Minimālais nodoklis no saimnieciskās darbības',
            'PielikumsD3/A19'  => 'D3 19. Apliekamais ienākums, atskaitot minimālo',
            'PielikumsD3/A20'  => 'D3 20. Apliekamais ienākums no saimnieciskās darbības',

            // ── D1 column totals (synthetic — summed across payer rows) ──
            'D1KOPA/BrutoIenem' => 'D1 Bruto ieņēmumi (kopā)',
            'D1KOPA/NodAvanss'  => 'D1 Avansā ieturētais nodoklis (kopā)',
            'D1KOPA/Vsaoi'      => 'D1 VSAOI (kopā)',

            // ── Header / general ──
            'DokIINGDv2/NeaplIenNorma'  => 'Neapliekamā minimuma norma',
            'NeaplIenNorma'             => 'Neapliekamā minimuma norma',
            'MinNodSD'                  => 'Minimālais nodoklis no saimnieciskās darbības',
            'TaksGads'                  => 'Taksācijas gads',
        ];
    }

    /**
     * Best label for a flattened EDS path, or null when unknown. Matches the longest
     * "Section/Code" suffix after stripping repeated-sibling indexes.
     */
    public static function label(string $path): ?string
    {
        $norm = preg_replace('/\[\d+\]/', '', $path);
        $best = null;
        $bestLen = -1;

        foreach (self::map() as $suffix => $label) {
            if (($norm === $suffix || str_ends_with($norm, '/'.$suffix)) && strlen($suffix) > $bestLen) {
                $best = $label;
                $bestLen = strlen($suffix);
            }
        }

        return $best;
    }

    /** Label for the path, falling back to a trimmed raw path when unknown. */
    public static function labelOrPath(string $path, int $limit = 48): string
    {
        return self::label($path) ?? Str::limit($path, $limit);
    }
}
