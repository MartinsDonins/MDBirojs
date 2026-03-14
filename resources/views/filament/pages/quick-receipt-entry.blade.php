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
         MAIN FORM
    ═══════════════════════════════════════════════════════════════════ --}}
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

</x-filament-panels::page>
