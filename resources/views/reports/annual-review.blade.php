@php
    /** @var int $year */
    /** @var array $rows */
    /** @var array $summary */
    /** @var array $transfers */
    /** @var array $unmapped */
    /** @var array $ignored */
    $fmt = fn ($v) => number_format((float) $v, 2, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        @page { margin: 18px 16px; }
        body { font-size: 8px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { font-size: 8px; color: #666; margin-bottom: 8px; }

        .summary { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .summary td { border: 1px solid #cbd5e1; padding: 4px 6px; }
        .summary .lbl { color: #555; font-size: 7.5px; }
        .summary .val { font-size: 11px; font-weight: bold; }
        .pos { color: #047857; } .neg { color: #b91c1c; } .muted { color: #6b7280; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 2px 3px; }
        table.data th { background: #dbeafe; font-size: 7.5px; text-align: left; }
        td.r { text-align: right; } td.c { text-align: center; }

        tr.k-income { background: #ecfdf5; }
        tr.k-expense_deductible { background: #fef2f2; }
        tr.k-expense_nondeductible { background: #fff7ed; }
        tr.k-transfer { background: #eff6ff; }
        tr.k-income_unmapped, tr.k-expense_unmapped { background: #fee2e2; }

        .badge { font-weight: bold; }
        .ok { color: #047857; } .bad { color: #b91c1c; }

        h2 { font-size: 11px; margin: 14px 0 4px; border-bottom: 1px solid #94a3b8; padding-bottom: 2px; }
        .note { font-size: 7.5px; color: #6b7280; margin: 2px 0 6px; }
    </style>
</head>
<body>
    <h1>Gada darījumu pārskats — {{ $year }}. gads</h1>
    <div class="sub">Sagatavots: {{ now()->format('d.m.Y H:i') }} · Pārbaudes atskaite saimnieciskās darbības darījumu kontrolei</div>

    {{-- ── Summary ─────────────────────────────────────────────── --}}
    <table class="summary">
        <tr>
            <td><div class="lbl">Ieņēmumi (saimn.darb.)</div><div class="val pos">{{ $fmt($summary['income']) }} €</div></td>
            <td><div class="lbl">Izdevumi (attaisnotie)</div><div class="val neg">{{ $fmt($summary['expense_deductible']) }} €</div></td>
            <td><div class="lbl">Izdevumi (neattaisnotie)</div><div class="val neg">{{ $fmt($summary['expense_nondeductible']) }} €</div></td>
            <td><div class="lbl">Peļņa (apliekamā)</div><div class="val {{ $summary['profit'] >= 0 ? 'pos' : 'neg' }}">{{ $fmt($summary['profit']) }} €</div></td>
        </tr>
        <tr>
            <td><div class="lbl">Darījumi kopā</div><div class="val">{{ $summary['count'] }}</div></td>
            <td><div class="lbl">Pārskaitījumi</div><div class="val">{{ $summary['transfer_count'] }} <span class="muted">/ bez pretdar.: <span class="{{ $summary['transfer_unmatched'] ? 'bad' : 'ok' }}">{{ $summary['transfer_unmatched'] }}</span></span></div></td>
            <td><div class="lbl">Nav kartēti (jāpārbauda)</div><div class="val {{ $summary['unmapped_count'] ? 'bad' : 'ok' }}">{{ $summary['unmapped_count'] }}</div></td>
            <td><div class="lbl">Ignorēti (izslēgti)</div><div class="val muted">{{ $summary['ignored_count'] }}</div></td>
        </tr>
    </table>

    {{-- ── Chronological listing ───────────────────────────────── --}}
    <h2>Visi darījumi (hronoloģiski)</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width:3%">Nr.</th>
                <th style="width:6%">Datums</th>
                <th style="width:10%">Konts</th>
                <th style="width:14%">Partneris</th>
                <th style="width:20%">Apraksts</th>
                <th style="width:12%">Kategorija</th>
                <th style="width:14%">Veids</th>
                <th style="width:8%" class="r">Summa EUR</th>
                <th style="width:9%" class="c">Pretdarījums ↔</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="k-{{ $row['kind'] }}">
                    <td class="c">{{ $row['n'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['account'] }}</td>
                    <td>{{ $row['partner'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['category'] ?? '—' }}</td>
                    <td>{{ $row['label'] }}</td>
                    <td class="r {{ $row['amount'] >= 0 ? 'pos' : 'neg' }}">{{ $fmt($row['amount']) }}</td>
                    <td class="c">
                        @if($row['type'] === 'TRANSFER')
                            @if($row['counter_status'])
                                <span class="ok" title="{{ $row['counter_account'] }}">✔</span>
                            @else
                                <span class="bad">✗</span>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Transfer reconciliation ─────────────────────────────── --}}
    @if(count($transfers) > 0)
        <h2>Pārskaitījumu / kases pretdarījumu pārbaude</h2>
        <div class="note">Katram pārskaitījumam jābūt pretējam darījumam otrā kontā (piem., banka → kase un kase ← banka). ✗ nozīmē, ka pretdarījums nav sasaistīts — jāpārbauda.</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:6%">Datums</th>
                    <th style="width:16%">Konts</th>
                    <th style="width:8%" class="c">Virziens</th>
                    <th style="width:10%" class="r">Summa EUR</th>
                    <th style="width:16%">Pretkonts</th>
                    <th style="width:8%" class="c">Statuss</th>
                    <th style="width:30%">Apraksts</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transfers as $t)
                    <tr class="{{ $t['matched'] ? '' : 'k-expense_unmapped' }}">
                        <td>{{ $t['date'] }}</td>
                        <td>{{ $t['account'] }}</td>
                        <td class="c">{{ $t['direction'] }}</td>
                        <td class="r {{ $t['amount'] >= 0 ? 'pos' : 'neg' }}">{{ $fmt($t['amount']) }}</td>
                        <td>{{ $t['counter_account'] ?? '—' }}</td>
                        <td class="c badge {{ $t['matched'] ? 'ok' : 'bad' }}">{{ $t['matched'] ? '✔ sasaistīts' : '✗ NAV' }}</td>
                        <td>{{ $t['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── Unmapped ────────────────────────────────────────────── --}}
    @if(count($unmapped) > 0)
        <h2>Nekartētie ieņēmumi / izdevumi ({{ count($unmapped) }}) — jāpārbauda kategorija</h2>
        <div class="note">Šiem darījumiem nav piešķirta žurnāla kolonna (kategorijas vid_column = 0), tāpēc tie neparādās ne attaisnotajos, ne neattaisnotajos. Vai nu piešķiriet kategoriju, vai atzīmējiet kā "Ignorēts".</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:6%">Datums</th>
                    <th style="width:12%">Konts</th>
                    <th style="width:18%">Partneris</th>
                    <th style="width:30%">Apraksts</th>
                    <th style="width:14%">Veids</th>
                    <th style="width:10%" class="r">Summa EUR</th>
                </tr>
            </thead>
            <tbody>
                @foreach($unmapped as $row)
                    <tr class="k-income_unmapped">
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['account'] }}</td>
                        <td>{{ $row['partner'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td>{{ $row['label'] }}</td>
                        <td class="r">{{ $fmt($row['amount_abs']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── Ignored ─────────────────────────────────────────────── --}}
    @if(count($ignored) > 0)
        <h2>Ignorētie darījumi ({{ count($ignored) }}) — izslēgti no visiem aprēķiniem</h2>
        <div class="note">Šie darījumi atzīmēti kā "Ignorēts" un netiek skaitīti ne ienākumos, ne izdevumos, ne atlikumā. Rādīti tikai informācijai.</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:8%">Datums</th>
                    <th style="width:16%">Konts</th>
                    <th style="width:22%">Partneris</th>
                    <th style="width:38%">Apraksts</th>
                    <th style="width:16%" class="r">Summa</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ignored as $row)
                    <tr class="muted">
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['account'] }}</td>
                        <td>{{ $row['partner'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="r">{{ $fmt($row['amount']) }} {{ $row['currency'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
