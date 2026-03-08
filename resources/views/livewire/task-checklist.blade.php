<div class="space-y-0.5">

    {{-- Existing items --}}
    @forelse($items as $item)
        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 group transition-colors">

            {{-- Checkbox --}}
            <input
                type="checkbox"
                wire:click="toggle({{ $item['id'] }})"
                @checked($item['is_completed'])
                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 cursor-pointer flex-shrink-0 focus:ring-primary-500"
            />

            @if($editingId === $item['id'])
                {{-- ── Edit mode ── --}}
                <input
                    type="text"
                    wire:model="editingTitle"
                    wire:keydown.enter="saveEdit"
                    wire:keydown.escape="cancelEdit"
                    class="flex-1 text-sm bg-transparent border-0 border-b border-primary-500 focus:ring-0 focus:outline-none px-0 py-0 text-gray-900 dark:text-white placeholder-gray-400"
                    autofocus
                />
                <button
                    wire:click="saveEdit"
                    class="flex-shrink-0 text-success-600 hover:text-success-700 transition-colors"
                    title="Saglabāt"
                >
                    <x-heroicon-o-check class="w-4 h-4" />
                </button>
                <button
                    wire:click="cancelEdit"
                    class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                    title="Atcelt"
                >
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>

            @else
                {{-- ── View mode ── --}}
                <span
                    wire:click="startEdit({{ $item['id'] }})"
                    class="flex-1 text-sm cursor-pointer select-none
                        {{ $item['is_completed']
                            ? 'line-through text-gray-400 dark:text-gray-500'
                            : 'text-gray-800 dark:text-gray-200' }}"
                    title="Klikšķināt lai rediģētu"
                >{{ $item['title'] }}</span>

                <button
                    wire:click="startEdit({{ $item['id'] }})"
                    class="flex-shrink-0 opacity-0 group-hover:opacity-100 text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
                    title="Rediģēt"
                >
                    <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                </button>
                <button
                    wire:click="delete({{ $item['id'] }})"
                    wire:confirm="Dzēst šo apakšuzdevumu?"
                    class="flex-shrink-0 opacity-0 group-hover:opacity-100 text-gray-400 hover:text-danger-600 dark:hover:text-danger-400 transition-all"
                    title="Dzēst"
                >
                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                </button>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-400 dark:text-gray-500 italic px-2 py-1">Nav apakšuzdevumu</p>
    @endforelse

    {{-- Add new ──────────────────────────────────────────────── --}}
    @if($addingNew)
        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-primary-50 dark:bg-primary-950/20 border border-primary-200 dark:border-primary-800 mt-1">
            <div class="w-4 h-4 flex-shrink-0"></div>
            <input
                type="text"
                wire:model="newTitle"
                wire:keydown.enter="addNew"
                wire:keydown.escape="cancelNew"
                placeholder="Apakšuzdevuma nosaukums..."
                class="flex-1 text-sm bg-transparent border-0 border-b border-primary-500 focus:ring-0 focus:outline-none px-0 py-0 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                autofocus
            />
            <button
                wire:click="addNew"
                class="flex-shrink-0 text-success-600 hover:text-success-700 transition-colors"
                title="Pievienot"
            >
                <x-heroicon-o-check class="w-4 h-4" />
            </button>
            <button
                wire:click="cancelNew"
                class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                title="Atcelt"
            >
                <x-heroicon-o-x-mark class="w-4 h-4" />
            </button>
        </div>
    @else
        <button
            wire:click="$set('addingNew', true)"
            class="flex items-center gap-1.5 w-full px-2 py-1.5 text-sm text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-white/5 rounded-lg transition-colors mt-1"
        >
            <x-heroicon-o-plus-circle class="w-4 h-4" />
            Pievienot apakšuzdevumu
        </button>
    @endif

</div>
