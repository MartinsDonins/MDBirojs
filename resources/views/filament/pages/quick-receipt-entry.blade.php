<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════════════════════════
         PASTE ZONES
    ═══════════════════════════════════════════════════════════════════ --}}

    {{-- Zone 1: full rows (descriptions → new rows) --}}
    <div
        x-data="{ active: false }"
        class="mb-3 rounded-xl border-2 transition-colors duration-150"
        :class="active
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/30'
            : 'border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900'"
    >
        <div class="px-4 pt-3 pb-1 flex items-center gap-2">
            <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Apraksti → jaunas rindas</span>
            <span class="ml-auto text-xs text-gray-400">Ctrl+V šeit</span>
        </div>
        <textarea
            rows="3"
            placeholder="Ielīmē aprakstus šeit (Ctrl+V) — katrs kļūst par jaunu rindu&#10;&#10;Depo čeks nr.: 0842032&#10;Latvijas pasts čeks nr.: 0203959"
            class="w-full resize-none rounded-b-xl border-0 border-t border-gray-200 dark:border-gray-700 bg-transparent px-4 py-3 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
            @focus="active = true"
            @blur="active = false"
            @paste.prevent="
                const text = $event.clipboardData.getData('text/plain');
                if (text.trim()) { $wire.processBufferDirect(text); $event.target.value = ''; }
                active = false;
            "
            @keydown.enter.prevent
        ></textarea>
        <p class="px-4 pb-2 text-xs text-gray-400">
            Viens apraksts katrā rindā &nbsp;·&nbsp; vai pilna rinda ar Tab (3 vai 4 kolonnas):<br>
            <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">12.03.2026 [Tab] Apraksts [Tab] 45,50</code>
            &nbsp;vai&nbsp;
            <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">12.03.2026 [Tab] Partneris [Tab] Apraksts [Tab] 45,50</code>
        </p>
    </div>

    {{-- Zones 2 + 3: date column and amount column side by side --}}
    <div class="mb-6 grid grid-cols-2 gap-3">

        {{-- Zone 2: dates → fill date column of existing rows --}}
        <div
            x-data="{ active: false }"
            class="rounded-xl border-2 transition-colors duration-150"
            :class="active
                ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/30'
                : 'border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900'"
        >
            <div class="px-3 pt-2 pb-1 flex items-center gap-2">
                <x-heroicon-o-calendar-days class="w-4 h-4 text-amber-400" />
                <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Datumi</span>
                <span class="ml-auto text-xs text-gray-400">aizpilda kolonnu</span>
            </div>
            <textarea
                rows="4"
                placeholder="12.03.2026&#10;13.03.2026&#10;15.03.2026"
                class="w-full resize-none rounded-b-xl border-0 border-t border-gray-200 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
                @focus="active = true"
                @blur="active = false"
                @paste.prevent="
                    const text = $event.clipboardData.getData('text/plain');
                    if (text.trim()) { $wire.processDateBuffer(text); $event.target.value = ''; }
                    active = false;
                "
                @keydown.enter.prevent
            ></textarea>
            <p class="px-3 pb-2 text-xs text-gray-400">Formāts: <code class="font-mono">dd.mm.gggg</code></p>
        </div>

        {{-- Zone 3: amounts → fill amount column of existing rows --}}
        <div
            x-data="{ active: false }"
            class="rounded-xl border-2 transition-colors duration-150"
            :class="active
                ? 'border-green-400 bg-green-50 dark:bg-green-950/30'
                : 'border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900'"
        >
            <div class="px-3 pt-2 pb-1 flex items-center gap-2">
                <x-heroicon-o-currency-euro class="w-4 h-4 text-green-400" />
                <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Summas</span>
                <span class="ml-auto text-xs text-gray-400">aizpilda kolonnu</span>
            </div>
            <textarea
                rows="4"
                placeholder="45,50&#10;38,00&#10;12,99"
                class="w-full resize-none rounded-b-xl border-0 border-t border-gray-200 dark:border-gray-700 bg-transparent px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
                @focus="active = true"
                @blur="active = false"
                @paste.prevent="
                    const text = $event.clipboardData.getData('text/plain');
                    if (text.trim()) { $wire.processAmountBuffer(text); $event.target.value = ''; }
                    active = false;
                "
                @keydown.enter.prevent
            ></textarea>
            <p class="px-3 pb-2 text-xs text-gray-400">Atbalsta: <code class="font-mono">45,50</code> vai <code class="font-mono">45.50</code></p>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         ZONE 4: Excel import
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="mb-3 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900">
        <div class="px-4 pt-3 pb-1 flex items-center gap-2">
            <x-heroicon-o-table-cells class="w-4 h-4 text-violet-500" />
            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Excel imports</span>
            <span class="ml-auto text-xs text-gray-400">Darījumi + KII/KIO orderi automātiski</span>
        </div>
        <div class="px-4 pb-2 pt-1 flex flex-wrap items-center gap-3">
            <button
                type="button"
                wire:click="mountAction('excel_import')"
                class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 px-3 py-1.5 text-sm font-medium text-white transition-colors"
            >
                <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                Izvēlēties .xlsx failu...
            </button>
            <a href="/admin/excel-template/cash" target="_blank"
               class="inline-flex items-center gap-1 text-xs text-violet-500 hover:text-violet-700 underline underline-offset-2">
                📥 Lejupielādēt paraugu
            </a>
        </div>
        <p class="px-4 pb-2 text-xs text-gray-400">
            Kolonnas: <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">Datums · Konts · Tips · Partneris · Apraksts · Summa · Valūta</code>
        </p>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         EXCEL IMPORT PREVIEW
    ═══════════════════════════════════════════════════════════════════ --}}
    @if (!empty($previewRows))
    <div class="mb-6 rounded-xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">

        {{-- Header --}}
        <div class="px-4 py-3 flex flex-wrap items-center gap-3 bg-violet-50 dark:bg-violet-950/40 border-b border-violet-200 dark:border-violet-800">
            <x-heroicon-o-eye class="w-4 h-4 text-violet-500 shrink-0" />
            <span class="text-sm font-semibold text-violet-800 dark:text-violet-200">
                Priekšskatījums — {{ count($previewRows) }} rindas
            </span>
            @php
                $importCount = count(array_filter($previewRows, fn($r) => empty($r['errors']) && !$r['skip']));
                $errorCount  = count(array_filter($previewRows, fn($r) => !empty($r['errors'])));
            @endphp
            @if($importCount > 0)
                <span class="text-xs font-medium text-green-700 dark:text-green-400 bg-green-100 dark:bg-green-900/40 px-2 py-0.5 rounded-full">
                    {{ $importCount }} tiks importētas
                </span>
            @endif
            @if($errorCount > 0)
                <span class="text-xs font-medium text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/40 px-2 py-0.5 rounded-full">
                    {{ $errorCount }} ar kļūdām (izlaistas)
                </span>
            @endif
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800 text-left text-gray-500 dark:text-gray-400 uppercase tracking-wide text-[10px]">
                        <th class="px-2 py-2 w-8 text-center">#</th>
                        <th class="px-2 py-2 whitespace-nowrap">Datums</th>
                        <th class="px-2 py-2 whitespace-nowrap">Konts</th>
                        <th class="px-2 py-2 whitespace-nowrap">Tips</th>
                        <th class="px-2 py-2 whitespace-nowrap">Partneris</th>
                        <th class="px-2 py-2">Apraksts</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Summa</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">EUR</th>
                        <th class="px-2 py-2 whitespace-nowrap">Val.</th>
                        <th class="px-2 py-2 whitespace-nowrap">Kategorija</th>
                        <th class="px-2 py-2">Statuss</th>
                        <th class="px-2 py-2 text-center whitespace-nowrap">Izlaist</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($previewRows as $i => $row)
                        @php
                            $hasWarnings = !empty($row['warnings'] ?? []);
                            $hasErrors   = !empty($row['errors']);
                            $rowClass    = $row['skip']
                                ? 'opacity-35'
                                : ($hasErrors
                                    ? 'bg-red-50 dark:bg-red-950/20'
                                    : ($hasWarnings
                                        ? 'bg-amber-50 dark:bg-amber-950/20'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'));
                            $isForeign = ($row['currency'] ?? 'EUR') !== 'EUR';
                        @endphp
                        <tr class="transition-opacity {{ $rowClass }}">
                            <td class="px-2 py-1.5 text-center text-gray-400">{{ $row['row_num'] }}</td>
                            <td class="px-2 py-1.5 font-mono whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="px-2 py-1.5 whitespace-nowrap">{{ $row['account'] }}</td>
                            <td class="px-2 py-1.5 whitespace-nowrap">
                                @if($row['type'] === 'INCOME')
                                    <span class="inline-flex items-center gap-0.5 text-green-700 dark:text-green-400 font-semibold">
                                        ↓ Saņemts
                                    </span>
                                @elseif($row['type'] === 'EXPENSE')
                                    <span class="inline-flex items-center gap-0.5 text-red-600 dark:text-red-400 font-semibold">
                                        ↑ Izsniegts
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">{{ $row['type_raw'] }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-gray-600 dark:text-gray-400 max-w-[120px] truncate" title="{{ $row['partner'] }}">{{ $row['partner'] }}</td>
                            <td class="px-2 py-1.5 max-w-xs truncate" title="{{ $row['description'] }}">{{ $row['description'] }}</td>
                            <td class="px-2 py-1.5 text-right font-mono font-medium tabular-nums whitespace-nowrap {{ $isForeign ? 'text-amber-700 dark:text-amber-400' : '' }}">
                                @if($row['amount'] !== null)
                                    {{ number_format($row['amount'], 2, ',', ' ') }}
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right font-mono tabular-nums whitespace-nowrap {{ $isForeign ? 'font-semibold' : 'text-gray-400' }}">
                                @if(($row['amount_eur'] ?? null) !== null)
                                    @if($isForeign)
                                        <span title="Kurss: {{ $row['exchange_rate'] ?? '—' }}">
                                            {{ number_format($row['amount_eur'], 2, ',', ' ') }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">≡</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-2 py-1.5 {{ $isForeign ? 'text-amber-700 dark:text-amber-400 font-semibold' : 'text-gray-500' }} whitespace-nowrap">{{ $row['currency'] }}</td>
                            {{-- Category select --}}
                            <td class="px-2 py-1.5">
                                @if(!$row['skip'])
                                <select
                                    @change="$wire.updatePreviewCategory({{ $i }}, $event.target.value || null)"
                                    class="w-full min-w-[110px] rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-[11px] text-gray-700 dark:text-gray-300 px-1 py-0.5 focus:outline-none focus:ring-1 focus:ring-violet-400"
                                >
                                    <option value="">—</option>
                                    @foreach($categoryOptions as $catId => $catName)
                                        <option value="{{ $catId }}" @selected(($row['category_id'] ?? null) == $catId)>
                                            {{ $catName }}
                                        </option>
                                    @endforeach
                                </select>
                                @endif
                            </td>
                            <td class="px-2 py-1.5">
                                @if($hasErrors)
                                    <span class="text-red-500 text-[10px]" title="{{ implode(' · ', $row['errors']) }}">
                                        ✗ {{ implode(' · ', $row['errors']) }}
                                    </span>
                                @elseif($hasWarnings)
                                    <span class="text-amber-600 dark:text-amber-400 text-[10px]" title="{{ implode(' · ', $row['warnings']) }}">
                                        ⚠ {{ implode(' · ', $row['warnings']) }}
                                    </span>
                                @else
                                    <span class="text-green-600 dark:text-green-400 font-bold">✓</span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <input
                                    type="checkbox"
                                    wire:click="togglePreviewSkip({{ $i }})"
                                    @checked($row['skip'])
                                    class="rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 cursor-pointer"
                                >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Confirm / Cancel --}}
        <div class="px-4 py-3 border-t border-violet-200 dark:border-violet-800 flex flex-wrap items-center gap-3 bg-violet-50/50 dark:bg-violet-950/20">
            <button
                type="button"
                wire:click="confirmExcelImport"
                wire:loading.attr="disabled"
                wire:target="confirmExcelImport"
                class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 transition-colors"
            >
                <x-heroicon-s-check class="w-4 h-4" />
                <span wire:loading.remove wire:target="confirmExcelImport">
                    Apstiprināt importu ({{ $importCount }} rindas)
                </span>
                <span wire:loading wire:target="confirmExcelImport">
                    Saglabā...
                </span>
            </button>
            <button
                type="button"
                wire:click="cancelExcelImport"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
            >
                Atcelt
            </button>
            <p class="ml-auto text-xs text-gray-400">
                Atzīmē "Izlaist" rindas, ko nevēlies importēt. Darījumi ar kļūdām tiek izlaisti automātiski.
            </p>
        </div>

    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
         MAIN FORM
    ═══════════════════════════════════════════════════════════════════ --}}
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

</x-filament-panels::page>
