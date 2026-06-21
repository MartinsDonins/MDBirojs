{{-- Editable European-format amount input for a D3 manual row. Expects $key. --}}
<input
    type="text"
    inputmode="decimal"
    wire:model.blur="manual.{{ $key }}"
    class="fi-input w-36 text-right rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm tabular-nums"
    placeholder="0,00"
/>
