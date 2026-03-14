<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════════════════
         PASTE ZONE — intercepts Ctrl+V and converts lines to rows
    ═══════════════════════════════════════════════════════════ --}}
    <div
        x-data="{ active: false }"
        class="mb-6 rounded-xl border-2 transition-colors duration-150"
        :class="active
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/30'
            : 'border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900'"
    >
        <div class="px-4 pt-3 pb-1 flex items-center gap-2">
            <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-gray-400" />
            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                Ātrā ielīmēšana
            </span>
            <span class="ml-auto text-xs text-gray-400">
                Ctrl+V šeit → rindas parādās automātiski
            </span>
        </div>

        <textarea
            rows="3"
            placeholder="Ielīmē čeku aprakstus šeit (Ctrl+V)&#10;&#10;Piemērs:&#10;Depo čeks nr.: 0842032&#10;Latvijas pasts čeks nr.: 0203959"
            class="w-full resize-none rounded-b-xl border-0 border-t border-gray-200 dark:border-gray-700 bg-transparent px-4 py-3 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-0"
            @focus="active = true"
            @blur="active = false"
            @paste.prevent="
                const text = $event.clipboardData.getData('text/plain');
                if (text.trim()) {
                    $wire.processBufferDirect(text);
                    $event.target.value = '';
                }
                active = false;
            "
            @keydown.enter.prevent
        ></textarea>

        <div class="px-4 pb-2">
            <p class="text-xs text-gray-400">
                <strong>Viens apraksts</strong> katrā rindā &nbsp;·&nbsp;
                vai pilna rinda ar Tab: <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">12.03.2026 [Tab] Apraksts [Tab] 45,50</code>
            </p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         MAIN FORM
    ═══════════════════════════════════════════════════════════ --}}
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>

</x-filament-panels::page>
