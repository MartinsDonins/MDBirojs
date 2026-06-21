<x-filament-panels::page>
    @php
        $r   = $this->rows();
        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
    @endphp

    {{-- ════════ Top bar: taxpayer, year, PDF ════════ --}}
    <div class="flex flex-wrap items-end gap-4 mb-6">
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nodokļa maksātājs</label>
            <input type="text" wire:model.blur="taxpayerName" placeholder="Vārds Uzvārds"
                class="fi-input block w-56 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Personas kods / NMR</label>
            <input type="text" wire:model.blur="taxpayerCode" placeholder="000000-00000"
                class="fi-input block w-44 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Taksācijas gads</label>
            <select wire:model.live="year"
                class="fi-select block w-32 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
                @forelse ($availableYears as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @empty
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforelse
            </select>
        </div>
        <div class="ml-auto">
            <a href="{{ route('reports.d3.pdf', ['year' => $year]) }}" target="_blank"
                class="fi-btn fi-btn-size-md inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-500">
                <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                Lejupielādēt PDF
            </a>
        </div>
    </div>

    {{-- ════════ Info banner ════════ --}}
    <div class="mb-6 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
        <strong>Uzmanību:</strong> šis ir palīgrīks priekšskatīšanai — dati no žurnāla
        (rindas&nbsp;4,&nbsp;5,&nbsp;6) ielasās automātiski, pārējās rindas ievadāmas manuāli.
        Pirms iesniegšanas pārbaudi summas VID EDS. Šī forma nav oficiāla deklarācija.
    </div>

    {{-- ════════ D3 form table ════════ --}}
    <div class="fi-section rounded-xl bg-white dark:bg-gray-900 shadow ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-3 w-16">Rinda</th>
                    <th class="px-4 py-3">Rādītāja nosaukums</th>
                    <th class="px-4 py-3 w-48 text-right">Summa (EUR)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                {{-- Row 1 (computed sum of 1.1–1.4) --}}
                <tr class="bg-gray-50/50 dark:bg-gray-800/40 font-medium">
                    <td class="px-4 py-2">1.</td>
                    <td class="px-4 py-2">Ieņēmumi no lauksaimnieciskās ražošanas un lauku tūrisma pakalpojumu sniegšanas</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $eur($r['row1']) }}</td>
                </tr>
                @foreach ([
                    'farm_1_1' => ['1.1.', 'ieņēmumi no lauksaimnieciskās ražošanas'],
                    'farm_1_2' => ['1.2.', 'ieņēmumi no iekšējo ūdeņu zivsaimniecības'],
                    'farm_1_3' => ['1.3.', 'ieņēmumi no lauku tūrisma pakalpojumu sniegšanas'],
                    'farm_1_4' => ['1.4.', 'ieņēmumi no atbalsta lauksaimniecībai un lauku attīstībai'],
                ] as $key => [$nr, $label])
                    <tr>
                        <td class="px-4 py-1.5 pl-8 text-gray-500">{{ $nr }}</td>
                        <td class="px-4 py-1.5 pl-8 text-gray-600 dark:text-gray-300">{{ $label }}</td>
                        <td class="px-4 py-1.5 text-right">
                            @include('filament.pages.partials.d3-input', ['key' => $key])
                        </td>
                    </tr>
                @endforeach

                {{-- Row 2 / 3 — farming expenses + losses (manual) --}}
                <tr>
                    <td class="px-4 py-2">2.</td>
                    <td class="px-4 py-2">Izdevumi, kas saistīti ar lauksaimniecisko ražošanu un lauku tūrisma pakalpojumu sniegšanu</td>
                    <td class="px-4 py-2 text-right">@include('filament.pages.partials.d3-input', ['key' => 'farm_2'])</td>
                </tr>
                <tr>
                    <td class="px-4 py-2">3.</td>
                    <td class="px-4 py-2">Iepriekšējo gadu saimnieciskās darbības zaudējumi (lauksaimniecība / lauku tūrisms)</td>
                    <td class="px-4 py-2 text-right">@include('filament.pages.partials.d3-input', ['key' => 'farm_3'])</td>
                </tr>

                {{-- Row 4 — non-taxable income (auto, memo) --}}
                <tr class="bg-emerald-50/40 dark:bg-emerald-900/10">
                    <td class="px-4 py-2">4.</td>
                    <td class="px-4 py-2">
                        Neapliekamie ienākumi
                        <span class="ml-2 text-xs text-emerald-600 dark:text-emerald-400">⤺ no žurnāla aiļu “{{ $nonTaxableAbbr }}”</span>
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums font-medium">{{ $eur($r['row4']) }}</td>
                </tr>

                {{-- Row 5 — taxable business income (auto) --}}
                <tr class="bg-emerald-50/40 dark:bg-emerald-900/10">
                    <td class="px-4 py-2">5.</td>
                    <td class="px-4 py-2">
                        Ieņēmumi no citiem saimnieciskās darbības veidiem
                        <span class="ml-2 text-xs text-emerald-600 dark:text-emerald-400">⤺ no žurnāla aiļu “{{ $incomeAbbr }}”</span>
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums font-semibold">{{ $eur($r['row5']) }}</td>
                </tr>

                {{-- Row 6 — deductible expenses (auto) --}}
                <tr class="bg-emerald-50/40 dark:bg-emerald-900/10">
                    <td class="px-4 py-2">6.</td>
                    <td class="px-4 py-2">
                        Izdevumi, kas saistīti ar citiem saimnieciskās darbības veidiem
                        <span class="ml-2 text-xs text-emerald-600 dark:text-emerald-400">⤺ no žurnāla aiļu “{{ $expenseAbbr }}”</span>
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums font-semibold">{{ $eur($r['row6']) }}</td>
                </tr>

                {{-- Row 7 — prior losses other (manual) --}}
                <tr>
                    <td class="px-4 py-2">7.</td>
                    <td class="px-4 py-2">Iepriekšējo gadu saimnieciskās darbības zaudējumi (citi darbības veidi)</td>
                    <td class="px-4 py-2 text-right">@include('filament.pages.partials.d3-input', ['key' => 'other_7'])</td>
                </tr>

                {{-- Row 8 — taxable income (computed) --}}
                <tr class="bg-primary-50 dark:bg-primary-900/20 font-semibold">
                    <td class="px-4 py-3">8.</td>
                    <td class="px-4 py-3">
                        Apliekamie ienākumi no saimnieciskās darbības
                        <span class="block text-xs font-normal text-gray-500">(1−1.4−2−3) + (5−6−7)</span>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums {{ $r['row8'] < 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                        {{ $eur($r['row8']) }}
                        @if ($r['row8'] < 0)
                            <span class="block text-xs font-normal">(zaudējumi — pārnesami)</span>
                        @endif
                    </td>
                </tr>

                {{-- Row 9 / 10 — foreign tax + min taxable (manual) --}}
                <tr>
                    <td class="px-4 py-2">9.</td>
                    <td class="px-4 py-2">Ārvalstīs samaksātais nodoklis</td>
                    <td class="px-4 py-2 text-right">@include('filament.pages.partials.d3-input', ['key' => 'foreign_9'])</td>
                </tr>
                <tr>
                    <td class="px-4 py-2">10.</td>
                    <td class="px-4 py-2">Minimālais apliekamais ienākums</td>
                    <td class="px-4 py-2 text-right">@include('filament.pages.partials.d3-input', ['key' => 'min_10'])</td>
                </tr>

                {{-- Row 11 — taxable income minus minimum (computed) --}}
                <tr class="bg-primary-50 dark:bg-primary-900/20 font-semibold">
                    <td class="px-4 py-3">11.</td>
                    <td class="px-4 py-3">
                        Apliekamais ienākums, atskaitot minimālo apliekamo ienākumu
                        <span class="block text-xs font-normal text-gray-500">(8 − 10)</span>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ $eur($r['row11']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
