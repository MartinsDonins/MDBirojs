<x-filament-panels::page>

    {{-- Integration disabled warning --}}
    @if(!$integrationEnabled)
        <div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-sm text-amber-800 dark:text-amber-300 flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5 shrink-0" />
            CoreDigify integrācija ir atspējota. Aktivizēt var <a href="{{ route('filament.admin.pages.settings-page') }}" class="underline font-medium">Iestatījumos → CoreDigify savienojums</a>.
        </div>
    @endif

    {{-- Stats row --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @foreach([
            ['label' => 'Kopā', 'value' => $stats['total'], 'color' => 'gray'],
            ['label' => 'Nosūtīti', 'value' => $stats['sent'], 'color' => 'success'],
            ['label' => 'Gaida', 'value' => $stats['pending'], 'color' => 'warning'],
            ['label' => 'Kļūdas', 'value' => $stats['error'], 'color' => 'danger'],
        ] as $stat)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                <div class="text-2xl font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400">{{ $stat['value'] }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Filters + actions toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        {{-- Year filter --}}
        <select wire:model.live="filterYear" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500 focus:border-primary-500">
            @foreach($this->getAvailableYears() as $yr)
                <option value="{{ $yr }}">{{ $yr }}</option>
            @endforeach
        </select>

        {{-- Status filter --}}
        <div class="flex gap-1">
            @foreach(['all' => 'Visi', 'pending' => 'Gaida', 'sent' => 'Nosūtīti', 'error' => 'Kļūdas'] as $val => $label)
                <button wire:click="$set('filterStatus', '{{ $val }}')"
                    class="px-3 py-1.5 text-xs rounded-full font-medium transition-colors
                        {{ $filterStatus === $val
                            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="ml-auto flex gap-2">
            {{ ($this->testConnectionAction)([]) }}
            {{ ($this->syncPendingAction)([]) }}
            @if($stats['error'] > 0)
                {{ ($this->syncErrorsAction)([]) }}
            @endif
        </div>
    </div>

    {{-- Transactions table --}}
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 text-[11px] uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2.5 text-left">Datums</th>
                    <th class="px-3 py-2.5 text-left">Maksātājs</th>
                    <th class="px-3 py-2.5 text-left">Apraksts / Atsauce</th>
                    <th class="px-3 py-2.5 text-right">Summa EUR</th>
                    <th class="px-3 py-2.5 text-center">Kases orderis</th>
                    <th class="px-3 py-2.5 text-center">Statuss</th>
                    <th class="px-3 py-2.5 text-center">Darbība</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($transactions as $tx)
                    <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-3 py-2 whitespace-nowrap text-xs font-mono">{{ $tx['occurred_at'] }}</td>
                        <td class="px-3 py-2 max-w-[160px] truncate" title="{{ $tx['counterparty_name'] }}">{{ $tx['counterparty_name'] }}</td>
                        <td class="px-3 py-2 max-w-[220px]">
                            @if($tx['reference'])
                                <div class="text-[10px] font-mono text-blue-600 dark:text-blue-400">{{ $tx['reference'] }}</div>
                            @endif
                            <div class="text-xs text-gray-500 truncate" title="{{ $tx['description'] }}">{{ $tx['description'] }}</div>
                        </td>
                        <td class="px-3 py-2 text-right font-medium whitespace-nowrap">{{ $tx['amount_eur'] }} €</td>
                        <td class="px-3 py-2 text-center text-xs font-mono text-green-700 dark:text-green-400">
                            {{ $tx['cash_order_number'] ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if($tx['sync_status'] === 'sent')
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400"
                                    title="Nosūtīts: {{ $tx['coredigify_sent_at'] }}">
                                    ✓ Nosūtīts
                                </span>
                            @elseif($tx['sync_status'] === 'error')
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400"
                                    title="{{ $tx['coredigify_sync_error'] }}">
                                    ! Kļūda
                                </span>
                            @else
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    ⟳ Gaida
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center" @click.stop>
                            @if($tx['sync_status'] !== 'sent')
                                {{ ($this->resendTransactionAction)(['id' => $tx['id']]) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                            Nav atbilstošu darījumu {{ $filterYear }}. gadā
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
