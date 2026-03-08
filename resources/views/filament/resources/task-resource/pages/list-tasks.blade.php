<x-filament-panels::page>

    {{-- Stats widgets --}}
    <x-filament-widgets::widgets
        :widgets="$this->getVisibleHeaderWidgets()"
        :columns="$this->getHeaderWidgetsColumns()"
    />

    {{-- Tab switcher --}}
    <div class="flex items-center gap-2 mt-2 mb-4">
        <button
            wire:click="setActiveView('list')"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition-colors
                {{ $activeView === 'list'
                    ? 'bg-primary-600 text-white shadow-sm'
                    : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700' }}"
        >
            <x-heroicon-o-list-bullet class="w-4 h-4" />
            Saraksts
        </button>
        <button
            wire:click="setActiveView('kanban')"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition-colors
                {{ $activeView === 'kanban'
                    ? 'bg-primary-600 text-white shadow-sm'
                    : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700' }}"
        >
            <x-heroicon-o-view-columns class="w-4 h-4" />
            Kanban
        </button>
    </div>

    {{-- ── LIST VIEW ─────────────────────────────────────────────────── --}}
    @if($activeView === 'list')
        {{ $this->table }}
    @endif

    {{-- ── KANBAN VIEW ──────────────────────────────────────────────── --}}
    @if($activeView === 'kanban')
        @php
            $kanbanData = $this->getKanbanData();
            $columns = [
                'open'        => ['label' => 'Atvērts',  'color' => 'blue',   'icon' => '○', 'bg' => 'bg-blue-50 dark:bg-blue-950/30',   'border' => 'border-blue-200 dark:border-blue-800'],
                'in_progress' => ['label' => 'Procesā',  'color' => 'amber',  'icon' => '▶', 'bg' => 'bg-amber-50 dark:bg-amber-950/30', 'border' => 'border-amber-200 dark:border-amber-800'],
                'completed'   => ['label' => 'Pabeigts', 'color' => 'green',  'icon' => '✓', 'bg' => 'bg-green-50 dark:bg-green-950/30', 'border' => 'border-green-200 dark:border-green-800'],
                'cancelled'   => ['label' => 'Atcelts',  'color' => 'gray',   'icon' => '✕', 'bg' => 'bg-gray-50 dark:bg-gray-800/40',   'border' => 'border-gray-200 dark:border-gray-700'],
            ];
            $priorityIcons = ['low' => '▼', 'medium' => '■', 'high' => '▲', 'urgent' => '⚡'];
            $priorityColors = [
                'low'    => 'text-blue-500',
                'medium' => 'text-amber-500',
                'high'   => 'text-orange-500',
                'urgent' => 'text-red-600 font-bold',
            ];
        @endphp

        <div
            x-data="{
                initKanban() {
                    if (!window.Sortable) { setTimeout(() => this.initKanban(), 200); return; }
                    document.querySelectorAll('[data-kanban-col]').forEach(col => {
                        Sortable.create(col, {
                            group: 'kanban-tasks',
                            animation: 150,
                            ghostClass: 'kanban-ghost',
                            dragClass: 'kanban-drag',
                            handle: '.kanban-handle',
                            onEnd: (evt) => {
                                if (evt.from !== evt.to) {
                                    const taskId = parseInt(evt.item.dataset.taskId);
                                    const newStatus = evt.to.dataset.status;
                                    $wire.updateTaskStatus(taskId, newStatus);
                                }
                            }
                        });
                    });
                }
            }"
            x-init="$nextTick(() => initKanban())"
            @task-updated.window="$nextTick(() => initKanban())"
            class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4"
        >
            @foreach($columns as $status => $col)
                <div class="flex flex-col min-h-96">
                    {{-- Column header --}}
                    <div class="flex items-center justify-between px-3 py-2.5 rounded-t-xl {{ $col['bg'] }} border {{ $col['border'] }} border-b-0">
                        <div class="flex items-center gap-2">
                            <span class="text-{{ $col['color'] }}-600 dark:text-{{ $col['color'] }}-400 font-mono text-sm">{{ $col['icon'] }}</span>
                            <span class="font-semibold text-sm text-gray-800 dark:text-gray-200">{{ $col['label'] }}</span>
                        </div>
                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold rounded-full bg-{{ $col['color'] }}-100 dark:bg-{{ $col['color'] }}-900/50 text-{{ $col['color'] }}-700 dark:text-{{ $col['color'] }}-300">
                            {{ count($kanbanData[$status]) }}
                        </span>
                    </div>

                    {{-- Drop zone --}}
                    <div
                        class="flex-1 p-2 rounded-b-xl border {{ $col['border'] }} {{ $col['bg'] }} min-h-32"
                        data-kanban-col
                        data-status="{{ $status }}"
                    >
                        <div class="space-y-2">
                            @forelse($kanbanData[$status] as $task)
                                <div
                                    data-task-id="{{ $task->id }}"
                                    class="group bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-3 cursor-grab active:cursor-grabbing hover:shadow-md transition-shadow"
                                >
                                    {{-- Drag handle + Priority indicator --}}
                                    <div class="flex items-start gap-2">
                                        <span class="kanban-handle mt-0.5 text-gray-300 dark:text-gray-600 hover:text-gray-500 cursor-grab text-xs select-none" title="Vilkt">⠿</span>

                                        <div class="flex-1 min-w-0">
                                            {{-- Title --}}
                                            <div class="flex items-start gap-1">
                                                <span class="{{ $priorityColors[$task->priority] ?? 'text-gray-400' }} text-xs mt-0.5 shrink-0">
                                                    {{ $priorityIcons[$task->priority] ?? '' }}
                                                </span>
                                                <a
                                                    href="{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task->id]) }}"
                                                    class="text-sm font-medium text-gray-800 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400 leading-snug line-clamp-2"
                                                >{{ $task->title }}</a>
                                            </div>

                                            {{-- Category --}}
                                            @if($task->category)
                                                <span class="inline-block mt-1.5 text-xs px-2 py-0.5 rounded-full font-medium"
                                                    style="background-color: {{ $task->category->color }}22; color: {{ $task->category->color }}; border: 1px solid {{ $task->category->color }}44">
                                                    {{ $task->category->name }}
                                                </span>
                                            @endif

                                            {{-- Due date --}}
                                            @if($task->due_at)
                                                <div class="flex items-center gap-1 mt-1.5 text-xs {{ $task->isOverdue() ? 'text-red-500 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                                    <x-heroicon-o-calendar class="w-3 h-3 shrink-0" />
                                                    {{ $task->isOverdue() ? '⚠ ' : '' }}{{ $task->due_at->format('d.m.Y') }}
                                                    @if($task->recurrence_type !== 'none')
                                                        <span class="text-gray-400" title="Atkārtojams">↺</span>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Checklist progress --}}
                                            @if($task->items->count() > 0)
                                                @php
                                                    $totalItems = $task->items->count();
                                                    $doneItems  = $task->items->where('is_completed', true)->count();
                                                    $pct        = $totalItems > 0 ? round($doneItems / $totalItems * 100) : 0;
                                                @endphp
                                                <div class="mt-2">
                                                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                                        <span>{{ $doneItems }}/{{ $totalItems }} apakšuzdevumi</span>
                                                        <span>{{ $pct }}%</span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1">
                                                        <div class="bg-green-500 h-1 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Quick status actions --}}
                                    <div class="flex items-center gap-1 mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-xs text-gray-400 mr-1">Pārvietot:</span>
                                        @foreach(['open' => '○ Atvērt', 'in_progress' => '▶ Sākt', 'completed' => '✓ Pabeigt', 'cancelled' => '✕ Atcelt'] as $s => $label)
                                            @if($s !== $status)
                                                <button
                                                    wire:click="updateTaskStatus({{ $task->id }}, '{{ $s }}')"
                                                    wire:loading.attr="disabled"
                                                    class="text-xs px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400 transition-colors whitespace-nowrap"
                                                    title="{{ $label }}"
                                                >{{ $label }}</button>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-600">
                                    <x-heroicon-o-inbox class="w-8 h-8 mb-2 opacity-40" />
                                    <span class="text-xs">Nav uzdevumu</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <style>
            .kanban-ghost { opacity: 0.35; background: #eff6ff !important; border: 2px dashed #93c5fd !important; border-radius: 0.5rem; }
            .kanban-drag  { opacity: 0.95; box-shadow: 0 8px 24px rgba(0,0,0,0.18); transform: rotate(1.5deg); }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    @endif

</x-filament-panels::page>
