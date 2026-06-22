<x-filament-panels::page>
    @php
        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
        $statusBadge = [
            'match'    => ['✓', 'text-emerald-600 dark:text-emerald-400'],
            'mismatch' => ['✗', 'text-red-600 dark:text-red-400 font-bold'],
            'eds_only' => ['EDS', 'text-amber-600 dark:text-amber-300'],
            'no_eds'   => ['', 'text-gray-300'],
        ];
        $sectionTitles = collect($this->sections())->pluck('title', 'code');
        // EDS-like tab order + short labels
        $tabOrder  = ['D1', 'D11', 'D2', 'D3', 'TAX', 'D4', 'D'];
        $tabLabels = ['D1' => 'D1', 'D11' => 'D1¹', 'D2' => 'D2', 'D3' => 'D3', 'TAX' => 'Nodokļi', 'D4' => 'D4', 'D' => 'D'];
        $kindColors = [
            'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
            'blue'    => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            'red'     => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
            'violet'  => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
            'gray'    => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        ];
    @endphp

    <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-4 py-2.5 text-xs text-amber-800 dark:text-amber-200">
        <strong>Palīgrīks.</strong> Augšupielādē EDS dokumentus (GID XML/HTML/PDF, IIN XML) — tie automātiski sašķirojas pa gadiem
        un sadaļām, un lauki sasaistās ar sistēmas datiem. Katra sadaļa (D1…D) rāda <em>sistēmas</em> un <em>EDS</em> vērtību blakus, lai validētu.
    </div>

    {{-- ════════ Global uploader ════════ --}}
    <label class="mb-5 flex flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-6 py-5 cursor-pointer hover:border-primary-400 hover:bg-primary-50/40 dark:hover:bg-primary-900/10 transition">
        <x-heroicon-o-arrow-up-tray class="w-6 h-6 text-gray-400" />
        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Augšupielādēt EDS dokumentus</span>
        <span class="text-xs text-gray-400">GID XML · HTML · PDF · IIN XML — vairāki faili reizē, sašķirojas automātiski</span>
        <input type="file" multiple accept=".xml,.html,.htm,.pdf,text/xml,application/xml,text/html,application/pdf"
            class="hidden" wire:model="docs" />
        <span wire:loading wire:target="docs" class="text-xs text-primary-600 mt-1">Augšupielādē un apstrādā…</span>
    </label>

    {{-- ════════ Year accordion ════════ --}}
    <div class="space-y-3">
        @forelse ($availableYears as $year)
            @php
                $open  = in_array($year, $expandedYears, true);
                $docs  = $open ? $this->documentsFor($year) : collect();
            @endphp
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
                        $present  = collect($tabOrder)->filter(fn ($c) => $grouped->has($c))->values();
                    @endphp
                    <div class="border-t border-gray-100 dark:border-gray-800 px-4 py-4"
                         x-data="{ tab: 'all' }">
                        <div class="mx-auto max-w-3xl space-y-4">

                            {{-- ── Document library ── --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">Augšupielādētie EDS dokumenti</div>
                                @if ($docs->isEmpty())
                                    <p class="text-xs text-gray-400">Nav dokumentu šim gadam. Augšupielādē augšā — fails sašķirosies šeit.</p>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($docs as $doc)
                                            <div class="inline-flex items-center gap-1.5 rounded-lg {{ $kindColors[$doc->kindColor()] ?? $kindColors['gray'] }} pl-2 pr-1 py-1 text-xs">
                                                <a href="{{ route('gid.document', $doc) }}" target="_blank" class="inline-flex items-center gap-1.5 hover:underline" title="{{ $doc->filename }}">
                                                    <x-dynamic-component :component="$doc->kindIcon()" class="w-3.5 h-3.5" />
                                                    <span class="font-medium">{{ $doc->kindLabel() }}</span>
                                                    @if ($doc->kind === 'iin_xml' && isset($doc->meta['taxable']))
                                                        <span class="opacity-70">· apl. {{ $eur($doc->meta['taxable']) }}</span>
                                                    @endif
                                                </a>
                                                <button type="button" wire:click="deleteDocument({{ $doc->id }})"
                                                    wire:confirm="Dzēst dokumentu {{ $doc->filename }}?"
                                                    class="ml-0.5 rounded p-0.5 hover:bg-black/10 dark:hover:bg-white/10" title="Dzēst">
                                                    <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            @if (! $hasEds)
                                <p class="text-sm text-gray-500">Vēl nav GID XML šim gadam — augšupielādē, lai salīdzinātu pret EDS.</p>
                            @endif

                            {{-- ── Validation summary ── --}}
                            @if ($hasEds)
                                @php $sm = $compareData[$year]['summary']; @endphp
                                <div class="flex flex-wrap items-center gap-3 text-xs">
                                    <span class="text-emerald-600">✓ Sakrīt: {{ $sm['match'] }}</span>
                                    <span class="text-red-600 font-semibold">✗ Nesakrīt: {{ $sm['mismatch'] }}</span>
                                    <span class="text-gray-400">Bez kartējuma: {{ $sm['no_eds'] }}</span>
                                </div>
                            @endif

                            {{-- ── EDS-like section tabs ── --}}
                            <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
                                <button type="button" @click="tab = 'all'"
                                    :class="tab === 'all' ? 'border-primary-500 text-primary-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="px-3 py-1.5 text-sm border-b-2 -mb-px transition">Kopskats</button>
                                @foreach ($present as $code)
                                    <button type="button" @click="tab = '{{ $code }}'"
                                        :class="tab === '{{ $code }}' ? 'border-primary-500 text-primary-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="px-3 py-1.5 text-sm border-b-2 -mb-px transition"
                                        title="{{ $sectionTitles[$code] ?? $code }}">{{ $tabLabels[$code] ?? $code }}</button>
                                @endforeach
                            </div>

                            {{-- ── Section blocks ── --}}
                            @foreach ($present as $code)
                                <div x-show="tab === 'all' || tab === '{{ $code }}'" x-cloak class="space-y-1">
                                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $sectionTitles[$code] ?? $code }}</h3>
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700">
                                                <th class="py-1.5 pr-3 text-left font-semibold">Lauks</th>
                                                <th class="py-1.5 px-2 text-right font-semibold w-32">Sistēma</th>
                                                <th class="py-1.5 px-2 text-right font-semibold w-32">EDS fails</th>
                                                <th class="py-1.5 pl-2 text-left font-semibold">@if ($hasEds) Darbība @endif</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($grouped[$code] as $row)
                                                @php [$sLabel, $sClass] = $statusBadge[$row['status']]; @endphp
                                                <tr class="border-b border-gray-100 dark:border-gray-800 {{ $row['status'] === 'mismatch' ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
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
                                                                        <option value="{{ $path }}">{{ \App\Services\Gid\GidEdsLabels::labelOrPath($path, 40) }} = {{ $val }}</option>
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
                                    </table>
                                </div>
                            @endforeach

                            {{-- ── Unmapped EDS fields (collapsed) ── --}}
                            @if (! empty($unmapped))
                                <details class="text-xs">
                                    <summary class="text-gray-500 cursor-pointer">Nepiesaistītie EDS lauki ({{ count($unmapped) }})</summary>
                                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-0.5 text-gray-600 dark:text-gray-400">
                                        @foreach ($unmapped as $path => $val)
                                            <div class="flex justify-between gap-2 border-b border-dashed border-gray-100 dark:border-gray-800 py-0.5" title="{{ $path }}">
                                                <span class="truncate">{{ \App\Services\Gid\GidEdsLabels::labelOrPath($path) }}</span>
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
                                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" /> Lejupielādēt D3 PDF (sistēma)
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-gray-500">Nav datu — augšupielādē EDS dokumentus vai importē darījumus žurnālā.</p>
        @endforelse
    </div>
</x-filament-panels::page>
