<x-filament-panels::page>
    @php
        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
        $sourceBadge = [
            'journal'  => ['Žurnāls', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
            'tax'      => ['Nodoklis', 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
            'computed' => ['Aprēķins', 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
            'manual'   => ['Manuāls', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
        ];
        $statusBadge = [
            'match'    => ['✓', 'text-emerald-600 dark:text-emerald-400'],
            'mismatch' => ['✗', 'text-red-600 dark:text-red-400 font-bold'],
            'eds_only' => ['EDS', 'text-amber-600 dark:text-amber-300'],
            'no_eds'   => ['', 'text-gray-300'],
        ];
        $sectionTitles = collect($this->sections())->pluck('title', 'code');
    @endphp

    <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-4 py-2.5 text-xs text-amber-800 dark:text-amber-200">
        <strong>Palīgrīks.</strong> Sistēmas aile rēķinās no žurnāla (zaļās/zilās/violetās rindas);
        dzeltenās ievada manuāli vai pārņem no EDS. EDS aile parāda VID faila vērtību — sarkanās rindas atšķiras.
    </div>

    {{-- ════════ Year accordion ════════ --}}
    <div class="space-y-3">
        @forelse ($availableYears as $year)
            @php $open = in_array($year, $expandedYears, true); @endphp
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">

                {{-- Header --}}
                <button type="button" wire:click="toggleYear({{ $year }})"
                    class="w-full flex items-center justify-between px-5 py-3.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-chevron-right class="w-5 h-5 transition-transform {{ $open ? 'rotate-90' : '' }}" />
                        <span class="text-lg font-bold">{{ $year }}. gads</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        @if ($open && isset($systemData[$year]))
                            <span class="text-gray-500 hidden sm:inline">Apliekamie (D3): <strong class="text-gray-800 dark:text-gray-100">{{ $eur($systemData[$year]['d3_taxable']) }}</strong></span>
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
                    @php
                        $hasEds   = $compareData[$year]['has_eds'] ?? false;
                        $rows     = collect($compareData[$year]['rows'] ?? []);
                        $grouped  = $rows->groupBy('section');
                        $unmapped = $compareData[$year]['unmapped'] ?? [];
                    @endphp
                    <div class="border-t border-gray-100 dark:border-gray-800 px-4 py-4">
                        <div class="mx-auto max-w-3xl space-y-4">

                            {{-- ── Toolbar: summary + EDS upload ── --}}
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-3 text-xs">
                                    @if ($hasEds)
                                        @php $sm = $compareData[$year]['summary']; @endphp
                                        <span class="text-emerald-600">✓ Sakrīt: {{ $sm['match'] }}</span>
                                        <span class="text-red-600 font-semibold">✗ Nesakrīt: {{ $sm['mismatch'] }}</span>
                                        <span class="text-gray-400">Bez kartējuma: {{ $sm['no_eds'] }}</span>
                                    @else
                                        <span class="text-gray-400">EDS fails nav pievienots</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    @if ($compareData[$year]['eds_meta'] ?? false)
                                        <span class="text-gray-400">
                                            {{ $compareData[$year]['eds_meta']['filename'] ?? '' }}
                                            · {{ $compareData[$year]['eds_meta']['imported_at'] ?? '' }}
                                        </span>
                                    @endif
                                    <label class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 px-2.5 py-1 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700">
                                        <x-heroicon-o-arrow-up-tray class="w-3.5 h-3.5" />
                                        {{ $hasEds ? 'Aizvietot EDS' : 'Pievienot EDS XML' }}
                                        <input type="file" accept=".xml,text/xml,application/xml" class="hidden"
                                            wire:model="eds.{{ $year }}" />
                                    </label>
                                </div>
                            </div>
                            <div wire:loading wire:target="eds.{{ $year }}" class="text-xs text-gray-500">Lādē XML…</div>

                            {{-- ── Unified two-column table: System vs EDS ── --}}
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs uppercase text-gray-500 border-b-2 border-gray-200 dark:border-gray-700">
                                        <th class="py-1.5 pr-3 text-left font-semibold">Lauks</th>
                                        <th class="py-1.5 px-2 text-right font-semibold w-32">Sistēma</th>
                                        <th class="py-1.5 px-2 text-right font-semibold w-32">EDS fails</th>
                                        <th class="py-1.5 pl-2 text-left font-semibold">@if ($hasEds) Darbība @endif</th>
                                    </tr>
                                </thead>
                                @foreach ($grouped as $sectionCode => $sectionRows)
                                    <tbody>
                                        <tr class="bg-gray-50 dark:bg-gray-800/40">
                                            <td colspan="4" class="py-1 px-2 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                {{ $sectionTitles[$sectionCode] ?? $sectionCode }}
                                            </td>
                                        </tr>
                                        @foreach ($sectionRows as $row)
                                            @php [$sLabel, $sClass] = $statusBadge[$row['status']]; @endphp
                                            <tr class="border-b border-gray-100 dark:border-gray-800 {{ $row['status'] === 'mismatch' ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                                                {{-- Field label --}}
                                                <td class="py-1 pr-3 text-gray-700 dark:text-gray-300">{{ $row['label'] }}</td>

                                                {{-- System value (editable for manual) --}}
                                                <td class="py-1 px-2 text-right">
                                                    @if ($row['source'] === 'manual')
                                                        <input type="text" inputmode="decimal"
                                                            wire:model.blur="manual.{{ $year }}.{{ $row['key'] }}"
                                                            class="fi-input w-28 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-xs tabular-nums px-2 py-1"
                                                            placeholder="0,00" />
                                                    @else
                                                        <span class="tabular-nums font-medium">{{ $eur($row['system']) }}</span>
                                                    @endif
                                                </td>

                                                {{-- EDS value + status --}}
                                                <td class="py-1 px-2 text-right tabular-nums {{ $row['status'] === 'mismatch' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                                    @if ($row['eds'] !== null)
                                                        <span class="{{ $sClass }} mr-0.5">{{ $sLabel }}</span>{{ $eur($row['eds']) }}
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>

                                                {{-- Action --}}
                                                <td class="py-1 pl-2">
                                                    @if ($row['adoptable'])
                                                        <button type="button" wire:click="adoptField({{ $year }}, '{{ $row['key'] }}')"
                                                            class="text-xs text-primary-600 hover:underline whitespace-nowrap">Pārņemt →</button>
                                                    @elseif ($hasEds && $row['status'] === 'no_eds' && $row['source'] === 'manual')
                                                        <div class="flex items-center gap-1">
                                                            <select wire:model="assignTarget.{{ $year }}.{{ $row['key'] }}"
                                                                class="fi-select text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 max-w-[150px] py-0.5">
                                                                <option value="">— piesaistīt EDS —</option>
                                                                @foreach ($unmapped as $path => $val)
                                                                    <option value="{{ $path }}">{{ \Illuminate\Support\Str::limit($path, 32) }} = {{ $val }}</option>
                                                                @endforeach
                                                            </select>
                                                            <button type="button" wire:click="assignPath({{ $year }}, '{{ $row['key'] }}')"
                                                                class="text-xs text-primary-600 hover:underline">OK</button>
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                @endforeach
                            </table>

                            {{-- ── Unmapped EDS fields (collapsed) ── --}}
                            @if (! empty($unmapped))
                                <details class="text-xs">
                                    <summary class="text-gray-500 cursor-pointer">Nepiesaistītie EDS lauki ({{ count($unmapped) }})</summary>
                                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-0.5 text-gray-600 dark:text-gray-400">
                                        @foreach ($unmapped as $path => $val)
                                            <div class="flex justify-between gap-2 border-b border-dashed border-gray-100 dark:border-gray-800 py-0.5">
                                                <span class="truncate">{{ $path }}</span>
                                                <span class="tabular-nums shrink-0">{{ $val }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif

                            {{-- D3 PDF --}}
                            <div class="text-right pt-1">
                                <a href="{{ route('reports.d3.pdf', ['year' => $year]) }}" target="_blank"
                                    class="text-xs text-primary-600 hover:underline inline-flex items-center gap-1">
                                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" /> Lejupielādēt D3 PDF
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-gray-500">Nav datu — vispirms importē darījumus žurnālā.</p>
        @endforelse
    </div>
</x-filament-panels::page>
