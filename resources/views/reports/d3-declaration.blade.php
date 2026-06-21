@php
    /** @var int $year */
    /** @var array $rows */
    /** @var string $income_abbr */
    /** @var string $expense_abbr */
    /** @var string $non_taxable_abbr */
    /** @var string $taxpayer_name */
    /** @var string $taxpayer_code */
    $fmt = fn ($v) => number_format((float) $v, 2, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        @page { margin: 28px 30px; }
        body { font-size: 9.5px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .sub { font-size: 8.5px; color: #555; margin-bottom: 10px; }

        .hdr { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .hdr td { padding: 3px 6px; font-size: 9px; }
        .hdr .lbl { color: #666; width: 130px; }
        .hdr .val { font-weight: bold; border-bottom: 1px solid #999; }

        table.d3 { width: 100%; border-collapse: collapse; }
        table.d3 th, table.d3 td { border: 1px solid #b9c2cf; padding: 4px 6px; vertical-align: top; }
        table.d3 th { background: #dbeafe; font-size: 8.5px; text-align: left; }
        td.nr { width: 38px; color: #444; }
        td.amt { width: 110px; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tr.sub td { color: #555; padding-left: 22px; font-size: 9px; }
        tr.auto { background: #ecfdf5; }
        tr.total td { background: #eef2ff; font-weight: bold; }
        .neg { color: #b91c1c; }
        .tag { font-size: 7.5px; color: #059669; }
        .disc { margin-top: 12px; font-size: 7.5px; color: #6b7280; line-height: 1.4; }
    </style>
</head>
<body>
    <h1>D pielikums D3 — Ienākumi no saimnieciskās darbības</h1>
    <div class="sub">Taksācijas gads: <strong>{{ $year }}</strong> · Sagatavots: {{ now()->format('d.m.Y H:i') }}</div>

    <table class="hdr">
        <tr>
            <td class="lbl">Nodokļa maksātājs</td>
            <td class="val">{{ $taxpayer_name !== '' ? $taxpayer_name : '—' }}</td>
            <td class="lbl">Personas kods / NMR</td>
            <td class="val">{{ $taxpayer_code !== '' ? $taxpayer_code : '—' }}</td>
        </tr>
    </table>

    <table class="d3">
        <thead>
            <tr>
                <th class="nr">Rinda</th>
                <th>Rādītāja nosaukums</th>
                <th style="text-align:right;">Summa (EUR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="nr">1.</td>
                <td>Ieņēmumi no lauksaimnieciskās ražošanas un lauku tūrisma pakalpojumu sniegšanas</td>
                <td class="amt">{{ $fmt($rows['row1']) }}</td>
            </tr>
            <tr class="sub"><td class="nr">1.1.</td><td>ieņēmumi no lauksaimnieciskās ražošanas</td><td class="amt">{{ $fmt($rows['row1_1']) }}</td></tr>
            <tr class="sub"><td class="nr">1.2.</td><td>ieņēmumi no iekšējo ūdeņu zivsaimniecības</td><td class="amt">{{ $fmt($rows['row1_2']) }}</td></tr>
            <tr class="sub"><td class="nr">1.3.</td><td>ieņēmumi no lauku tūrisma pakalpojumu sniegšanas</td><td class="amt">{{ $fmt($rows['row1_3']) }}</td></tr>
            <tr class="sub"><td class="nr">1.4.</td><td>ieņēmumi no atbalsta lauksaimniecībai un lauku attīstībai</td><td class="amt">{{ $fmt($rows['row1_4']) }}</td></tr>

            <tr><td class="nr">2.</td><td>Izdevumi, kas saistīti ar lauksaimniecisko ražošanu un lauku tūrisma pakalpojumu sniegšanu</td><td class="amt">{{ $fmt($rows['row2']) }}</td></tr>
            <tr><td class="nr">3.</td><td>Iepriekšējo gadu saimnieciskās darbības zaudējumi (lauksaimniecība / lauku tūrisms)</td><td class="amt">{{ $fmt($rows['row3']) }}</td></tr>

            <tr class="auto">
                <td class="nr">4.</td>
                <td>Neapliekamie ienākumi <span class="tag">(no žurnāla aiļu “{{ $non_taxable_abbr }}”)</span></td>
                <td class="amt">{{ $fmt($rows['row4']) }}</td>
            </tr>
            <tr class="auto">
                <td class="nr">5.</td>
                <td>Ieņēmumi no citiem saimnieciskās darbības veidiem <span class="tag">(no žurnāla aiļu “{{ $income_abbr }}”)</span></td>
                <td class="amt">{{ $fmt($rows['row5']) }}</td>
            </tr>
            <tr class="auto">
                <td class="nr">6.</td>
                <td>Izdevumi, kas saistīti ar citiem saimnieciskās darbības veidiem <span class="tag">(no žurnāla aiļu “{{ $expense_abbr }}”)</span></td>
                <td class="amt">{{ $fmt($rows['row6']) }}</td>
            </tr>
            <tr><td class="nr">7.</td><td>Iepriekšējo gadu saimnieciskās darbības zaudējumi (citi darbības veidi)</td><td class="amt">{{ $fmt($rows['row7']) }}</td></tr>

            <tr class="total">
                <td class="nr">8.</td>
                <td>Apliekamie ienākumi no saimnieciskās darbības &nbsp; <span style="font-weight:normal;font-size:8px;">(1−1.4−2−3) + (5−6−7)</span></td>
                <td class="amt {{ $rows['row8'] < 0 ? 'neg' : '' }}">{{ $fmt($rows['row8']) }}</td>
            </tr>

            <tr><td class="nr">9.</td><td>Ārvalstīs samaksātais nodoklis</td><td class="amt">{{ $fmt($rows['row9']) }}</td></tr>
            <tr><td class="nr">10.</td><td>Minimālais apliekamais ienākums</td><td class="amt">{{ $fmt($rows['row10']) }}</td></tr>

            <tr class="total">
                <td class="nr">11.</td>
                <td>Apliekamais ienākums, atskaitot minimālo apliekamo ienākumu &nbsp; <span style="font-weight:normal;font-size:8px;">(8 − 10)</span></td>
                <td class="amt">{{ $fmt($rows['row11']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="disc">
        Zaļās rindas (4, 5, 6) aizpildītas automātiski no saimnieciskās darbības ieņēmumu un izdevumu uzskaites žurnāla.
        Pārējās rindas ievadītas manuāli. Šis ir palīgrīks un nav oficiāla deklarācija — summas pirms iesniegšanas
        jāpārbauda VID Elektroniskās deklarēšanas sistēmā (EDS).
    </div>
</body>
</html>
