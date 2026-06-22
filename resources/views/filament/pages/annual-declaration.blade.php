<x-filament-panels::page>
    @php
        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
        $sourceBadge = [
            'journal'  => ['Žurnāls', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
            'tax'      => ['Nodoklis', 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
            'computed' => ['Aprēķins', 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
            'manual'   => ['Manuāls / EDS', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
        ];
        $statusBadge = [
            'match'    => ['✓ Sakrīt', 'text-emerald-700 dark:text-emerald-300'],
            'mismatch' => ['✗ Nesakrīt', 'text-red-700 dark:text-red-400 font-semibold'],
            'eds_only' => ['EDS only', 'text-amber-700 dark:text-amber-300'],
            'no_eds'   => ['—', 'text-gray-400'],
        ];
    @endphp

    <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
        <strong>Palīgrīks.</strong> Zaļās/zilās rindas aprēķinās no žurnāla; dzeltenās ievada manuāli vai
        pārņem no EDS XML. Salīdzināšana parāda, kur sistēmas un EDS dati atšķiras — tā vieglāk atrast kļūdas.
        Pirms iesniegšanas pārbaudi VID EDS.
    </div>

    {{-- ════════ Year accordion ════════ --}}
    <div class="space-y-3">
        @forelse ($availableYears as $year)
            @php $open = in_array($year, $expandedYears, true); @endphp
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">

                {{-- Header --}}
                <button type="button" wire:click="toggleYear({{ $year }})"
                    class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-chevron-right class="w-5 h-5 transition-transform {{ $open ? 'rotate-90' : '' }}" />
                        <span class="text-lg font-bold">{{ $year }}. gads</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        @if ($open && isset($systemData[$year]))
                            <span class="text-gray-500">Apliekamie (D3): <strong class="text-gray-800 dark:text-gray-100">{{ $eur($systemData[$year]['d3_taxable']) }}</strong></span>
                            <span class="text-gray-500">IIN: <strong class="text-gray-800 dark:text-gray-100">{{ $eur($systemData[$year]['tax_iin']) }}</strong></span>
                        @endif
                        @if ($open && ($compareData[$year]['has_eds'] ?? false))
                            @php $sm = $compareData[$year]['summary']; @endphp
                            @if ($sm['mismatch'] > 0)
                                <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 text-xs font-semibold">{{ $sm['mismatch'] }} nesakrit.</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs font-semibold">EDS ✓</span>
                            @endif
                        @endif
                    </div>
                </button>

                {{-- Body --}}
                @if ($open)
                    <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-5 space-y-6">

                        {{-- ── Declaration sections ── --}}
                        @foreach ($this->sections() as $section)
                            <div>
                                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">{{ $section['title'] }}</h3>
                                <table class="w-full text-sm">
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($section['fields'] as $f)
                                            @php [$bLabel, $bClass] = $sourceBadge[$f['source']]; @endphp
                                            <tr>
                                                <td class="py-1.5 pr-3 w-8">
                                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] {{ $bClass }}">{{ $bLabel }}</span>
                                                </td>
                                                <td class="py-1.5 pr-3 text-gray-700 dark:text-gray-300">{{ $f['label'] }}</td>
                                                <td class="py-1.5 text-right w-44">
                                                    @if ($f['source'] === 'manual')
                                                        <input type="text" inputmode="decimal"
                                                            wire:model.blur="manual.{{ $year }}.{{ $f['key'] }}"
                                                            class="fi-input w-36 text-right rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm tabular-nums"
                                                            placeholder="0,00" />
                                                    @else
                                                        <span class="tabular-nums font-medium">{{ $eur($systemData[$year][$f['key']] ?? 0) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach

                        {{-- ── EDS comparison ── --}}
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200">Pārbaude pret EDS deklarāciju</h3>
                                <div class="flex items-center gap-3 text-xs">
                                    @if ($compareData[$year]['eds_meta'] ?? false)
                                        <span class="text-gray-500">
                                            {{ $compareData[$year]['eds_meta']['filename'] ?? '' }}
                                            · {{ $compareData[$year]['eds_meta']['imported_at'] ?? '' }}
                                        </span>
                                    @endif
                                    <label class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-1.5 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700">
                                        <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                                        Pievienot EDS XML
                                        <input type="file" accept=".xml,text/xml,application/xml" class="hidden"
                                            wire:model="eds.{{ $year }}" />
                                    </label>
                                </div>
                            </div>

                            <div wire:loading wire:target="eds.{{ $year }}" class="text-xs text-gray-500 mb-2">Lādē XML…</div>

                            @if ($compareData[$year]['has_eds'] ?? false)
                                @php $sm = $compareData[$year]['summary']; @endphp
                                <div class="flex gap-3 text-xs mb-3">
                                    <span class="text-emerald-600">Sakrīt: {{ $sm['match'] }}</span>
                                    <span class="text-red-600 font-semibold">Nesakrīt: {{ $sm['mismatch'] }}</span>
                                    <span class="text-gray-400">Bez EDS kartējuma: {{ $sm['no_eds'] }}</span>
                                </div>

                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700">
                                            <th class="py-2 pr-3">Lauks</th>
                                            <th class="py-2 pr-3 text-right">Sistēma</th>
                                            <th class="py-2 pr-3 text-right">EDS</th>
                                            <th class="py-2 pr-3 text-center">Statuss</th>
                                            <th class="py-2">Darbība</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($compareData[$year]['rows'] as $row)
                                            @php [$sLabel, $sClass] = $statusBadge[$row['status']]; @endphp
                                            <tr class="{{ $row['status'] === 'mismatch' ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                                                <td class="py-1.5 pr-3 text-gray-700 dark:text-gray-300">
                                                    <span class="text-[10px] text-gray-400 mr-1">{{ $row['section'] }}</span>{{ $row['label'] }}
                                                </td>
                                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ $eur($row['system']) }}</td>
                                                <td class="py-1.5 pr-3 text-right tabular-nums">
                                                    {{ $row['eds'] !== null ? $eur($row['eds']) : '—' }}
                                                </td>
                                                <td class="py-1.5 pr-3 text-center {{ $sClass }}">{{ $sLabel }}</td>
                                                <td class="py-1.5">
                                                    @if ($row['adoptable'])
                                                        <button type="button" wire:click="adoptField({{ $year }}, '{{ $row['key'] }}')"
                                                            class="text-xs text-primary-600 hover:underline">Pārņemt EDS →</button>
                                                    @elseif ($row['status'] === 'no_eds' && $row['source'] === 'manual')
                                                        <div class="flex items-center gap-1">
                                                            <select wire:model="assignTarget.{{ $year }}.{{ $row['key'] }}"
                                                                class="fi-select text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 max-w-[180px]">
                                                                <option value="">— EDS lauks —</option>
                                                                @foreach ($compareData[$year]['unmapped'] as $path => $val)
                                                                    <option value="{{ $path }}">{{ \Illuminate\Support\Str::limit($path, 40) }} = {{ $val }}</option>
                                                                @endforeach
                                                            </select>
                                                            <button type="button" wire:click="assignPath({{ $year }}, '{{ $row['key'] }}')"
                                                                class="text-xs text-primary-600 hover:underline">Piesaistīt</button>
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                @if (! empty($compareData[$year]['unmapped']))
                                    <details class="mt-3">
                                        <summary class="text-xs text-gray-500 cursor-pointer">Nepiesaistītie EDS lauki ({{ count($compareData[$year]['unmapped']) }})</summary>
                                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                                            @foreach ($compareData[$year]['unmapped'] as $path => $val)
                                                <div class="flex justify-between gap-2 border-b border-dashed border-gray-100 dark:border-gray-800 py-0.5">
                                                    <span class="truncate">{{ $path }}</span>
                                                    <span class="tabular-nums shrink-0">{{ $val }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            @else
                                <p class="text-sm text-gray-500">Vēl nav pievienota EDS deklarācija šim gadam. Pievieno EDS XML, lai salīdzinātu.</p>
                            @endif
                        </div>

                        {{-- D3 PDF --}}
                        <div class="text-right">
                            <a href="{{ route('reports.d3.pdf', ['year' => $year]) }}" target="_blank"
                                class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" /> Lejupielādēt D3 PDF
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-gray-500">Nav datu — vispirms importē darījumus žurnālā.</p>
        @endforelse
    </div>
</x-filament-panels::page>
