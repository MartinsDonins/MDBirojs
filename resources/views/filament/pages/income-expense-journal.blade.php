<x-filament-panels::page>
    @if($selectedYear === null)
        {{-- All Years View --}}
        <div class="mb-6">
            <div class="text-center mb-4">
                <h2 class="text-2xl font-bold">
                    GADU PĀRSKATS
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    Saimnieciskās darbības ieņēmumu un izdevumu žurnāls
                </p>
            </div>

            <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <table class="fi-ta-table w-full text-start divide-y divide-gray-200 dark:divide-white/5">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-start text-sm font-medium text-gray-950 dark:text-white">Gads</th>
                            <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white">Ieņēmumi</th>
                            <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white">Izdevumi</th>
                            <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white">Rezultāts</th>
                            <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white">Atlikums gada beigās</th>
                            <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach($yearlySummary as $yearData)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-4 py-3 text-sm font-bold text-gray-950 dark:text-white">
                                    {{ $yearData['year'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-end text-success-600 dark:text-success-400">
                                    {{ number_format($yearData['income'], 2, ',', ' ') }} €
                                </td>
                                <td class="px-4 py-3 text-sm text-end text-danger-600 dark:text-danger-400">
                                    {{ number_format($yearData['expense'], 2, ',', ' ') }} €
                                </td>
                                <td class="px-4 py-3 text-sm text-end font-medium {{ $yearData['result'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ number_format($yearData['result'], 2, ',', ' ') }} €
                                </td>
                                <td class="px-4 py-3 text-sm text-end font-bold {{ $yearData['end_balance'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ number_format($yearData['end_balance'], 2, ',', ' ') }} €
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <x-filament::button
                                        size="sm"
                                        icon="heroicon-o-eye"
                                        wire:click="selectYear({{ $yearData['year'] }})"
                                    >
                                        Atvērt
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    @elseif($selectedMonth === null)
        {{-- Year Summary View --}}
        <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $this->getTitle() }}
                </h2>
                <div class="flex gap-2">
                    <x-filament::button wire:click="mountAction('createTransaction')">
                        Pievienot darījumu
                    </x-filament::button>
                </div>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Kopējie ieņēmumi {{ $selectedYear }}. gadā
                    </div>
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400 mt-2">
                        {{ number_format($summary['total_income'], 2, ',', ' ') }} EUR
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Kopējie izdevumi {{ $selectedYear }}. gadā
                    </div>
                    <div class="text-2xl font-bold text-danger-600 dark:text-danger-400 mt-2">
                        {{ number_format($summary['total_expense'], 2, ',', ' ') }} EUR
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Atlikums uz {{ $selectedYear }}. gada beigām
                    </div>
                    <div class="text-2xl font-bold {{ $summary['balance'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }} mt-2">
                        {{ number_format($summary['balance'], 2, ',', ' ') }} EUR
                    </div>
                </x-filament::card>
            </div>
        </div>

        {{-- Monthly Summary Table --}}
        @php
            $incomeColCount  = count($journalIncomeColumns);
            $expenseColCount = count($journalExpenseColumns);
        @endphp
        <div x-data="{}"
             x-init="if (!Alpine.store('yearView')) { Alpine.store('yearView', { expandedMonths: [] }); }"
             class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300 dark:border-gray-700 text-xs">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th rowspan="2" class="px-3 py-2 border border-gray-300 dark:border-gray-700 text-start text-sm font-medium text-gray-950 dark:text-white align-bottom" style="min-width:120px">Mēnesis</th>
                        <th colspan="{{ $incomeColCount + 1 }}" class="px-1 py-2 border border-gray-300 dark:border-gray-700 bg-green-50 dark:bg-green-900/30 text-center text-sm font-medium text-gray-950 dark:text-white">Ieņēmumi (EUR)</th>
                        <th colspan="{{ $expenseColCount + 1 }}" class="px-1 py-2 border border-gray-300 dark:border-gray-700 bg-red-50 dark:bg-red-900/30 text-center text-sm font-medium text-gray-950 dark:text-white">Izdevumi (EUR)</th>
                        <th rowspan="2" class="px-3 py-2 border border-gray-300 dark:border-gray-700 text-end text-sm font-medium text-gray-950 dark:text-white align-bottom">Bilance</th>
                        @foreach($accounts as $acc)
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/30 text-[10px] font-medium text-gray-800 dark:text-gray-200 text-center align-bottom" style="min-width:80px" title="{{ $acc->name }}">{{ mb_substr($acc->name, 0, 14) }}</th>
                        @endforeach
                        <th rowspan="2" class="px-2 py-2 border border-gray-300 dark:border-gray-700 align-bottom w-16"></th>
                    </tr>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-center text-[10px]">
                        @foreach($journalIncomeColumns as $col)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300" title="{{ $col['name'] }}">{{ $col['abbr'] }}</th>
                        @endforeach
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold text-gray-900 dark:text-gray-100">Kopā</th>
                        @foreach($journalExpenseColumns as $col)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300" title="{{ $col['name'] }}">{{ $col['abbr'] }}</th>
                        @endforeach
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold text-gray-900 dark:text-gray-100">Kopā</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($monthlySummary as $summary)
                        {{-- Month summary row (clickable to expand categories) --}}
                        <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer border-b border-gray-200 dark:border-white/5"
                            @click="$store.yearView.expandedMonths.includes({{ $summary['month_number'] }})
                                ? $store.yearView.expandedMonths = $store.yearView.expandedMonths.filter(m => m !== {{ $summary['month_number'] }})
                                : $store.yearView.expandedMonths.push({{ $summary['month_number'] }})">
                            <td class="px-3 py-2 text-sm font-medium text-gray-950 dark:text-white border border-gray-300 dark:border-gray-700">
                                <span class="flex items-center gap-1">
                                    <span x-text="$store.yearView && $store.yearView.expandedMonths.includes({{ $summary['month_number'] }}) ? '▾' : '▸'" class="text-gray-400 text-[10px]"></span>
                                    {{ $summary['month'] }}
                                </span>
                            </td>
                            @foreach($journalIncomeColumns as $i => $col)
                            <td class="px-2 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 text-success-700 dark:text-success-400">
                                @if(isset($summary['income_cols'][$i]) && $summary['income_cols'][$i] > 0){{ number_format($summary['income_cols'][$i], 2, ',', ' ') }}@endif
                            </td>
                            @endforeach
                            <td class="px-2 py-2 text-xs text-end font-bold border border-gray-300 dark:border-gray-700 bg-green-50 dark:bg-green-900/10 text-success-700 dark:text-success-400">
                                @if($summary['income_kopaa'] > 0){{ number_format($summary['income_kopaa'], 2, ',', ' ') }}@endif
                            </td>
                            @foreach($journalExpenseColumns as $i => $col)
                            <td class="px-2 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 text-danger-700 dark:text-danger-400">
                                @if(isset($summary['expense_cols'][$i]) && $summary['expense_cols'][$i] > 0){{ number_format($summary['expense_cols'][$i], 2, ',', ' ') }}@endif
                            </td>
                            @endforeach
                            <td class="px-2 py-2 text-xs text-end font-bold border border-gray-300 dark:border-gray-700 bg-red-50 dark:bg-red-900/10 text-danger-700 dark:text-danger-400">
                                @if($summary['expense_kopaa'] > 0){{ number_format($summary['expense_kopaa'], 2, ',', ' ') }}@endif
                            </td>
                            <td class="px-3 py-2 text-xs text-end font-medium border border-gray-300 dark:border-gray-700 {{ $summary['balance'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ number_format($summary['balance'], 2, ',', ' ') }}
                            </td>
                            @foreach($accounts as $acc)
                            <td class="px-2 py-2 text-xs text-end font-medium border border-gray-300 dark:border-gray-700 {{ ($summary['account_balances'][$acc->id] ?? 0) < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ number_format($summary['account_balances'][$acc->id] ?? 0, 2, ',', ' ') }}
                            </td>
                            @endforeach
                            <td class="px-2 py-2 text-end border border-gray-300 dark:border-gray-700" @click.stop>
                                <x-filament::button size="xs" color="gray" icon="heroicon-o-eye"
                                    wire:click="viewMonthDetails({{ $summary['month_number'] }})">
                                    Skatīt
                                </x-filament::button>
                            </td>
                        </tr>

                        {{-- Expandable: per-category breakdown rows --}}
                        @foreach($summary['categories'] as $cat)
                            <tr x-show="$store.yearView && $store.yearView.expandedMonths.includes({{ $summary['month_number'] }})"
                                class="{{ $cat['type'] === 'INCOME' ? 'bg-green-50/40 dark:bg-green-900/5' : 'bg-red-50/40 dark:bg-red-900/5' }}">
                                <td class="pl-7 pr-2 py-1 text-[10px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400">
                                    <span class="{{ $cat['type'] === 'INCOME' ? 'text-success-500' : 'text-danger-500' }} mr-1">{{ $cat['type'] === 'INCOME' ? '↑' : '↓' }}</span>
                                    {{ $cat['name'] }}
                                    @if($cat['vid_column'] > 0)<span class="text-gray-400 text-[9px] ml-1">(kol.{{ $cat['vid_column'] }})</span>@endif
                                </td>
                                @if($cat['type'] === 'INCOME')
                                    @foreach($journalIncomeColumns as $col)
                                    <td class="px-2 py-1 text-xs text-end border border-gray-200 dark:border-gray-700 text-success-600 dark:text-success-400">
                                        @if(in_array($cat['vid_column'], $col['vid_columns'])){{ number_format($cat['total'], 2, ',', ' ') }}@endif
                                    </td>
                                    @endforeach
                                    <td class="px-2 py-1 text-xs text-end font-medium border border-gray-200 dark:border-gray-700 bg-green-50 dark:bg-green-900/10 text-success-600 dark:text-success-400">
                                        {{ number_format($cat['total'], 2, ',', ' ') }}
                                    </td>
                                    <td colspan="{{ $expenseColCount + 1 }}" class="border border-gray-200 dark:border-gray-700"></td>
                                @else
                                    <td colspan="{{ $incomeColCount + 1 }}" class="border border-gray-200 dark:border-gray-700"></td>
                                    @foreach($journalExpenseColumns as $col)
                                    <td class="px-2 py-1 text-xs text-end border border-gray-200 dark:border-gray-700 text-danger-600 dark:text-danger-400">
                                        @if(in_array($cat['vid_column'], $col['vid_columns'])){{ number_format($cat['total'], 2, ',', ' ') }}@endif
                                    </td>
                                    @endforeach
                                    <td class="px-2 py-1 text-xs text-end font-medium border border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/10 text-danger-600 dark:text-danger-400">
                                        {{ number_format($cat['total'], 2, ',', ' ') }}
                                    </td>
                                @endif
                                <td class="border border-gray-200 dark:border-gray-700"></td>
                                @foreach($accounts as $acc)
                                <td class="border border-gray-200 dark:border-gray-700"></td>
                                @endforeach
                                <td class="border border-gray-200 dark:border-gray-700"></td>
                            </tr>
                        @endforeach
                    @endforeach

                    {{-- Total Row --}}
                    <tr class="bg-gray-100 dark:bg-white/5 font-bold border-t-2 border-gray-400">
                        <td class="px-3 py-2 text-sm text-gray-950 dark:text-white border border-gray-300 dark:border-gray-700">GADA KOPĀ</td>
                        @foreach($journalIncomeColumns as $i => $col)
                        <td class="px-2 py-2 text-xs text-end text-success-700 dark:text-success-400 border border-gray-300 dark:border-gray-700">
                            {{ number_format(collect($monthlySummary)->sum(fn($m) => $m['income_cols'][$i] ?? 0), 2, ',', ' ') }}
                        </td>
                        @endforeach
                        <td class="px-2 py-2 text-xs text-end font-bold bg-green-50 dark:bg-green-900/10 text-success-700 dark:text-success-400 border border-gray-300 dark:border-gray-700">
                            {{ number_format(collect($monthlySummary)->sum('income_kopaa'), 2, ',', ' ') }}
                        </td>
                        @foreach($journalExpenseColumns as $i => $col)
                        <td class="px-2 py-2 text-xs text-end text-danger-700 dark:text-danger-400 border border-gray-300 dark:border-gray-700">
                            {{ number_format(collect($monthlySummary)->sum(fn($m) => $m['expense_cols'][$i] ?? 0), 2, ',', ' ') }}
                        </td>
                        @endforeach
                        <td class="px-2 py-2 text-xs text-end font-bold bg-red-50 dark:bg-red-900/10 text-danger-700 dark:text-danger-400 border border-gray-300 dark:border-gray-700">
                            {{ number_format(collect($monthlySummary)->sum('expense_kopaa'), 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 {{ (collect($monthlySummary)->last()['balance'] ?? 0) >= 0 ? 'text-gray-900 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ number_format(collect($monthlySummary)->last()['balance'] ?? 0, 2, ',', ' ') }}
                        </td>
                        @foreach($accounts as $acc)
                        <td class="px-2 py-2 text-xs text-end font-bold border border-gray-300 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/10 {{ (collect($monthlySummary)->last()['account_balances'][$acc->id] ?? 0) < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">
                            {{ number_format(collect($monthlySummary)->last()['account_balances'][$acc->id] ?? 0, 2, ',', ' ') }}
                        </td>
                        @endforeach
                        <td class="border border-gray-300 dark:border-gray-700"></td>
                    </tr>
                </tbody>
            </table>
        </div>

    @else
        {{-- Month Detail View --}}
        @php
            $latvianMonths = [1=>'Janvāris',2=>'Februāris',3=>'Marts',4=>'Aprīlis',5=>'Maijs',6=>'Jūnijs',7=>'Jūlijs',8=>'Augusts',9=>'Septembris',10=>'Oktobris',11=>'Novembris',12=>'Decembris'];
        @endphp
        <div class="mb-6">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                <x-filament::button color="gray" icon="heroicon-o-arrow-left" wire:click="backToYearSummary">
                    Gada kopsavilkums
                </x-filament::button>

                {{-- Month navigation --}}
                <div class="flex items-center gap-2">
                    <x-filament::button color="gray" icon="heroicon-o-chevron-left" wire:click="goToPrevMonth">
                        Iepriekšējais
                    </x-filament::button>
                    <span class="text-base font-bold text-gray-800 dark:text-gray-200 min-w-[160px] text-center">
                        {{ $latvianMonths[$selectedMonth] ?? '' }} {{ $selectedYear }}
                    </span>
                    <x-filament::button color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="goToNextMonth">
                        Nākošais
                    </x-filament::button>
                </div>

                <div class="flex gap-2">
                    <x-filament::button
                        wire:click="toggleInvalidFilter"
                        color="{{ $showOnlyInvalid ? 'danger' : 'gray' }}"
                        icon="{{ $showOnlyInvalid ? 'heroicon-o-x-circle' : 'heroicon-o-funnel' }}"
                        title="Filtrēt rindas bez analīzes kartēšanas">
                        {{ $showOnlyInvalid ? 'Rādīt visus' : 'Nekartētie' }}
                    </x-filament::button>
                    <x-filament::button wire:click="mountAction('createTransaction')" icon="heroicon-o-plus">
                        Pievienot darījumu
                    </x-filament::button>
                </div>
            </div>

            <div class="text-center mb-4">
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">
                    {{ strtoupper($latvianMonths[$selectedMonth] ?? '') }} {{ $selectedYear }}
                </h2>
            </div>

            {{-- Month Summary: compact single panel --}}
            @php
                $monthData = collect($monthlySummary)->firstWhere('month_number', $selectedMonth);
                $result = ($monthData['income'] ?? 0) - ($monthData['expense'] ?? 0);
                $incomeUncategorized  = $monthData ? max(0, ($monthData['income']  ?? 0) - ($monthData['income_kopaa']  ?? 0)) : 0;
                $expenseUncategorized = $monthData ? max(0, ($monthData['expense'] ?? 0) - ($monthData['expense_kopaa'] ?? 0)) : 0;
            @endphp

            @if($monthData)
            <div class="mb-4 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="flex divide-x divide-gray-200 dark:divide-white/10">

                    {{-- INCOME --}}
                    <div class="flex-1 min-w-0">
                        <div class="px-3 py-1 bg-green-50 dark:bg-green-900/20 flex justify-between items-center border-b border-green-100 dark:border-green-900/40">
                            <span class="text-[11px] font-semibold text-green-800 dark:text-green-300 uppercase tracking-wide">Ieņēmumi</span>
                            <span class="text-xs font-bold text-green-700 dark:text-green-400">{{ number_format($monthData['income'], 2, ',', ' ') }}</span>
                        </div>
                        <table class="w-full">
                            @foreach($journalIncomeColumns as $i => $col)
                                @php $ct = $monthData['income_cols'][$i] ?? 0; @endphp
                                <tr class="{{ $ct > 0 ? '' : 'opacity-35' }} border-b border-gray-100 dark:border-white/5">
                                    <td class="px-2 py-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-[1px]" title="{{ $col['name'] }}">
                                        <span class="font-mono text-gray-400 dark:text-gray-600">{{ $col['abbr'] }}</span>
                                        <span class="ml-1">{{ $col['name'] }}</span>
                                    </td>
                                    <td class="px-2 py-0.5 text-[11px] text-right font-medium text-green-700 dark:text-green-400 whitespace-nowrap w-24">{{ $ct > 0 ? number_format($ct, 2, ',', ' ') : '—' }}</td>
                                </tr>
                            @endforeach
                            @if($incomeUncategorized > 0.005)
                                <tr class="border-b border-orange-100 dark:border-orange-900/30 bg-orange-50/60 dark:bg-orange-900/10">
                                    <td class="px-2 py-0.5 text-[11px] text-orange-600 dark:text-orange-400 italic">Nav kartēti</td>
                                    <td class="px-2 py-0.5 text-[11px] text-right font-medium text-orange-600 dark:text-orange-400 w-24">{{ number_format($incomeUncategorized, 2, ',', ' ') }}</td>
                                </tr>
                            @endif
                            <tr class="bg-green-50/70 dark:bg-green-900/10">
                                <td class="px-2 py-1 text-[11px] font-semibold text-green-800 dark:text-green-300">Kopā kartēti</td>
                                <td class="px-2 py-1 text-[11px] text-right font-bold text-green-700 dark:text-green-400 w-24">{{ number_format($monthData['income_kopaa'], 2, ',', ' ') }}</td>
                            </tr>
                        </table>
                    </div>

                    {{-- EXPENSE --}}
                    <div class="flex-1 min-w-0">
                        <div class="px-3 py-1 bg-red-50 dark:bg-red-900/20 flex justify-between items-center border-b border-red-100 dark:border-red-900/40">
                            <span class="text-[11px] font-semibold text-red-800 dark:text-red-300 uppercase tracking-wide">Izdevumi</span>
                            <span class="text-xs font-bold text-red-700 dark:text-red-400">{{ number_format($monthData['expense'], 2, ',', ' ') }}</span>
                        </div>
                        <table class="w-full">
                            @foreach($journalExpenseColumns as $i => $col)
                                @php $ct = $monthData['expense_cols'][$i] ?? 0; @endphp
                                <tr class="{{ $ct > 0 ? '' : 'opacity-35' }} border-b border-gray-100 dark:border-white/5">
                                    <td class="px-2 py-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-[1px]" title="{{ $col['name'] }}">
                                        <span class="font-mono text-gray-400 dark:text-gray-600">{{ $col['abbr'] }}</span>
                                        <span class="ml-1">{{ $col['name'] }}</span>
                                    </td>
                                    <td class="px-2 py-0.5 text-[11px] text-right font-medium text-red-700 dark:text-red-400 whitespace-nowrap w-24">{{ $ct > 0 ? number_format($ct, 2, ',', ' ') : '—' }}</td>
                                </tr>
                            @endforeach
                            @if($expenseUncategorized > 0.005)
                                <tr class="border-b border-orange-100 dark:border-orange-900/30 bg-orange-50/60 dark:bg-orange-900/10">
                                    <td class="px-2 py-0.5 text-[11px] text-orange-600 dark:text-orange-400 italic">Nav kartēti</td>
                                    <td class="px-2 py-0.5 text-[11px] text-right font-medium text-orange-600 dark:text-orange-400 w-24">{{ number_format($expenseUncategorized, 2, ',', ' ') }}</td>
                                </tr>
                            @endif
                            <tr class="bg-red-50/70 dark:bg-red-900/10">
                                <td class="px-2 py-1 text-[11px] font-semibold text-red-800 dark:text-red-300">Kopā kartēti</td>
                                <td class="px-2 py-1 text-[11px] text-right font-bold text-red-700 dark:text-red-400 w-24">{{ number_format($monthData['expense_kopaa'], 2, ',', ' ') }}</td>
                            </tr>
                        </table>
                    </div>

                    {{-- BALANCE + ACCOUNTS --}}
                    <div class="w-44 shrink-0">
                        <div class="px-3 py-1 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-white/10">
                            <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Bilance</span>
                        </div>
                        <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-100 dark:border-white/5">
                            <span class="text-gray-500 dark:text-gray-400">Ieņēmumi</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">+{{ number_format($monthData['income'], 2, ',', ' ') }}</span>
                        </div>
                        <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-100 dark:border-white/5">
                            <span class="text-gray-500 dark:text-gray-400">Izdevumi</span>
                            <span class="text-red-600 dark:text-red-400 font-medium">−{{ number_format($monthData['expense'], 2, ',', ' ') }}</span>
                        </div>
                        <div class="px-3 py-1 flex justify-between border-b-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                            <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300">Rezultāts</span>
                            <span class="text-xs font-bold {{ $result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ ($result >= 0 ? '+' : '') . number_format($result, 2, ',', ' ') }}</span>
                        </div>
                        <div class="px-3 pt-1.5 pb-0.5">
                            <span class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Kontu atlikumi</span>
                        </div>
                        @foreach($accounts as $acc)
                            @php $bal = $monthData['account_balances'][$acc->id] ?? 0; @endphp
                            <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-100 dark:border-white/5 last:border-0">
                                <span class="text-gray-500 dark:text-gray-400 truncate mr-1" title="{{ $acc->name }}">{{ mb_substr($acc->name, 0, 14) }}</span>
                                <span class="font-medium whitespace-nowrap {{ $bal < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">{{ number_format($bal, 2, ',', ' ') }}</span>
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>
            @endif
        </div>

        {{-- Dynamic Account Journal Table --}}
        @php
            $incomeColCount    = count($journalIncomeColumns);
            $expenseColCount   = count($journalExpenseColumns);
            // 8 fixed cols + 3*accounts + income cols + 1 kopā + expense cols + 1 kopā + 1 atb
            $totalAnalysisCols = $incomeColCount + 1 + $expenseColCount + 1 + 1;
            $detailColSpan     = 8 + count($accounts) * 3 + $incomeColCount + 1 + $expenseColCount + 1 + 1;
        @endphp
        <div class="overflow-x-auto bg-white dark:bg-gray-900 p-4 rounded-lg shadow-sm"
             x-data="{}"
             x-init="if (!Alpine.store('journal')) { Alpine.store('journal', { expandedRows: [] }); }">
            <table class="w-full border-collapse border border-gray-300 dark:border-gray-700 text-xs">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-center text-[10px] font-semibold">
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom sticky left-0 bg-gray-100 dark:bg-gray-800 z-10 text-gray-900 dark:text-gray-100" style="min-width: 40px;">Nr.</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom sticky left-8 bg-gray-100 dark:bg-gray-800 z-10 text-gray-900 dark:text-gray-100" style="min-width: 65px;">Datums</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 100px;">Dok. nr.<br>un datums</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 120px;">Partneris</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 150px;">Apraksts</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 80px;">Kategorija</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100">Sasaite</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 40px;">Statuss</th>

                        {{-- Konto kolonnas --}}
                        @foreach($accounts as $acc)
                            <th colspan="3" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/30 text-gray-900 dark:text-gray-100">{{ $acc->name }}</th>
                        @endforeach

                        {{-- Ieņēmumu analīze --}}
                        <th colspan="{{ $incomeColCount + 1 }}" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-green-50 dark:bg-green-900/30 text-gray-900 dark:text-gray-100">Ieņēmumi (EUR)</th>

                        {{-- Izdevumu analīze --}}
                        <th colspan="{{ $expenseColCount + 1 }}" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-red-50 dark:bg-red-900/30 text-gray-900 dark:text-gray-100">Izdevumi (EUR)</th>

                        {{-- Atbilstība --}}
                        <th rowspan="3" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-center text-gray-900 dark:text-gray-100" title="Atbilstība: vai darījuma summa pilnībā iekļaujas analīzes kolonnās">Atb.</th>
                    </tr>
                    <tr class="bg-gray-50 dark:bg-gray-800/50 text-center text-[10px]">
                        {{-- Kontu apakškolonnas --}}
                        @foreach($accounts as $acc)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-green-600 dark:text-green-400 bg-gray-100 dark:bg-gray-800" title="Ieņēmumi">Ieņ.</th>
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-red-600 dark:text-red-400 bg-gray-100 dark:bg-gray-800" title="Izdevumi">Izd.</th>
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Atlikums">Atlikums</th>
                        @endforeach

                        {{-- Ieņēmumu analīzes apakškolonnas --}}
                        @foreach($journalIncomeColumns as $col)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="{{ $col['name'] }}">{{ $col['abbr'] }}</th>
                        @endforeach
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">Kopā</th>

                        {{-- Izdevumu analīzes apakškolonnas --}}
                        @foreach($journalExpenseColumns as $col)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="{{ $col['name'] }}">{{ $col['abbr'] }}</th>
                        @endforeach
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">Kopā</th>
                    </tr>

                    {{-- Column Numbers --}}
                    <tr class="bg-gray-100 dark:bg-gray-800 text-center text-[10px] text-gray-500 font-mono">
                        <th class="border border-gray-300 dark:border-gray-700 sticky left-0 z-20 bg-gray-100 dark:bg-gray-800">1</th>
                        <th class="border border-gray-300 dark:border-gray-700 sticky left-8 z-20 bg-gray-100 dark:bg-gray-800">2</th>
                        <th class="border border-gray-300 dark:border-gray-700">3</th>
                        <th class="border border-gray-300 dark:border-gray-700">4</th>
                        <th class="border border-gray-300 dark:border-gray-700">5</th>
                        <th class="border border-gray-300 dark:border-gray-700">6</th>
                        <th class="border border-gray-300 dark:border-gray-700">7</th>
                        <th class="border border-gray-300 dark:border-gray-700">8</th>

                        @php $colNum = 9; @endphp
                        @foreach($accounts as $acc)
                            <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                            <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                            <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        @endforeach

                        {{-- Income + expense analysis col numbers --}}
                        @foreach($journalIncomeColumns as $col)
                            <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        @endforeach
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        @foreach($journalExpenseColumns as $col)
                            <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        @endforeach
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900">
                    {{-- Opening Balances Row --}}
                    <tr class="bg-yellow-50 dark:bg-yellow-900/10 font-bold text-gray-700 dark:text-gray-300">
                        <td colspan="7" class="px-2 py-2 border border-gray-300 dark:border-gray-700 text-right text-xs">Sākuma atlikums:</td>
                        <td class="border border-gray-300 dark:border-gray-700"></td>
                        @foreach($accounts as $acc)
                            <td colspan="2" class="border border-gray-300 dark:border-gray-700"></td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right hover:bg-yellow-100 dark:hover:bg-yellow-800/30 cursor-pointer group/bal"
                                title="Klikšķiniet, lai labotu {{ $acc->name }} sākuma atlikumu">
                                <div wire:click="mountOpeningBalanceModal({{ $acc->id }})" class="flex items-center justify-end gap-1">
                                    <span>{{ number_format($opening_balances[$acc->id] ?? 0, 2, ',', ' ') }}</span>
                                    <span class="text-[8px] text-yellow-500 opacity-0 group-hover/bal:opacity-100">✎</span>
                                </div>
                            </td>
                        @endforeach
                        <td colspan="{{ $totalAnalysisCols }}" class="border border-gray-300 dark:border-gray-700"></td>
                    </tr>

                    @foreach($rows as $row)
                    @if(!$showOnlyInvalid || !$row['is_mapped'])
                        <tr wire:key="row-{{ $row['entry_number'] }}" class="group cursor-pointer {{ in_array($row['transaction_type'], ['EXPENSE', 'FEE']) ? 'bg-red-50/50 dark:bg-red-900/10' : '' }} hover:bg-blue-50 dark:hover:bg-blue-900/20"
                            @click="$store.journal.expandedRows.includes({{ $row['entry_number'] }}) ? $store.journal.expandedRows = $store.journal.expandedRows.filter(id => id !== {{ $row['entry_number'] }}) : $store.journal.expandedRows.push({{ $row['entry_number'] }})">

                            {{-- 1. Identifikācija --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-center sticky left-0 z-10 font-mono font-bold text-xs bg-white dark:bg-gray-900 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20 text-gray-900 dark:text-gray-100" title="Ieraksta Nr.">{{ $row['entry_number'] }}</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 whitespace-nowrap sticky left-8 z-10 bg-white dark:bg-gray-900 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20 text-gray-900 dark:text-gray-100">{{ $row['date'] }}</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-[10px] break-all text-gray-900 dark:text-gray-100">{{ $row['document_details'] }}</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-[10px] truncate max-w-[100px] text-gray-900 dark:text-gray-100" title="{{ $row['partner'] }}">{{ $row['partner'] }}</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-[10px] truncate max-w-[150px] text-gray-900 dark:text-gray-100" title="{{ $row['description'] }}">{{ $row['description'] }}</td>

                            {{-- Kategorija (Interactive) --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-[10px] hover:bg-gray-100 dark:hover:bg-gray-700 text-primary-600 dark:text-primary-400 cursor-pointer"
                                title="Klikšķiniet, lai mainītu kategoriju">
                                    @if($row['transaction_id'])
                                    <div class="w-full h-full hover:underline"
                                         @click.stop
                                         wire:click="mountCategoryModal({{ $row['transaction_id'] }})">
                                        {{ $row['category'] ?? '---' }}
                                    </div>
                                    @else
                                        {{ $row['category'] ?? '---' }}
                                    @endif
                            </td>

                            {{-- Sasaite (Interactive) --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-center hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer"
                                title="{{ $row['linked_account_name'] ? 'Sasaistīts ar: ' . $row['linked_account_name'] . ' — klikšķiniet, lai pārvaldītu' : 'Nav sasaistes — klikšķiniet, lai sasaistītu' }}">
                                @if($row['transaction_id'])
                                <div class="w-full h-full flex items-center justify-center"
                                     @click.stop
                                     wire:click="mountLinkModal({{ $row['transaction_id'] }})">
                                    @if($row['linked_account_name'])
                                        <span class="text-[9px] text-blue-600 dark:text-blue-400 font-medium leading-tight whitespace-nowrap">
                                            ↔ {{ $row['linked_account_name'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600 text-xs hover:text-gray-500 dark:hover:text-gray-400">—</span>
                                    @endif
                                </div>
                                @endif
                            </td>

                            {{-- Statuss (single click toggle, right-click for modal) --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-center cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                                title="{{ $row['status'] === 'COMPLETED' ? 'Apstiprināts — klikšķis: pārvērst Melnrakstā' : ($row['status'] === 'NEEDS_REVIEW' ? 'Nepieciešama pārbaude — klikšķis: Apstiprināt' : 'Melnraksts — klikšķis: Apstiprināt') }}">
                                @if($row['transaction_id'])
                                <div class="w-full h-full select-none"
                                     @click.stop
                                     wire:click="toggleStatus({{ $row['transaction_id'] }})">
                                    @if($row['status'] === 'COMPLETED')
                                        <span class="text-green-600 dark:text-green-400 text-lg font-bold">✓</span>
                                    @elseif($row['status'] === 'NEEDS_REVIEW')
                                        <span class="text-orange-500 text-lg font-bold">?</span>
                                    @else
                                        <span class="text-gray-400 text-lg">○</span>
                                    @endif
                                </div>
                                @endif
                            </td>

                            {{-- 2. Konti --}}
                            @foreach($accounts as $acc)
                                <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-green-600 dark:text-green-400 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20">
                                    @if($row['transaction_account_id'] == $acc->id && ($row['transaction_type'] == 'INCOME' || ($row['transaction_type'] == 'TRANSFER' && $row['transaction_amount'] > 0)))
                                        {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                    @endif
                                </td>
                                <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-red-600 dark:text-red-400 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20">
                                    @if($row['transaction_account_id'] == $acc->id && (in_array($row['transaction_type'], ['EXPENSE', 'FEE']) || ($row['transaction_type'] == 'TRANSFER' && $row['transaction_amount'] < 0)))
                                        {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                    @endif
                                </td>
                                <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right font-bold bg-gray-50 dark:bg-gray-800 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 {{ ($row['account_balances'][$acc->id] ?? 0) < 0 ? 'text-red-600' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ number_format($row['account_balances'][$acc->id] ?? 0, 2, ',', ' ') }}
                                </td>
                            @endforeach

                            {{-- 3. Ieņēmumu analīze (dynamic columns) --}}
                            @foreach($journalIncomeColumns as $col)
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="{{ $col['name'] }}">
                                @if($row['transaction_type'] == 'INCOME' && in_array($row['category_vid_column'], $col['vid_columns']))
                                    {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                @endif
                            </td>
                            @endforeach
                            {{-- Ieņēmumi Kopā --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right font-bold text-green-600 dark:text-green-400">
                                @if($row['transaction_type'] == 'INCOME' && $row['is_mapped'])
                                    {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                @endif
                            </td>

                            {{-- 4. Izdevumu analīze (dynamic columns) --}}
                            @foreach($journalExpenseColumns as $col)
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="{{ $col['name'] }}">
                                @if($row['transaction_type'] == 'EXPENSE' && in_array($row['category_vid_column'], $col['vid_columns']))
                                    {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                @endif
                            </td>
                            @endforeach
                            {{-- Izdevumi Kopā --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right font-bold text-red-600 dark:text-red-400">
                                @if($row['transaction_type'] == 'EXPENSE' && $row['is_mapped'])
                                    {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                @endif
                            </td>

                            {{-- 5. Atbilstības indikators --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-center">
                                @if($row['is_mapped'])
                                    <span class="text-green-600 dark:text-green-400 font-bold text-sm" title="Summa pilnībā iekļauta analīzes kolonnās">✓</span>
                                @else
                                    <span class="text-red-500 font-bold text-sm" title="Summa nav kartēta uz analīzes kolonnu — pārbaudiet kategoriju!">✗</span>
                                @endif
                            </td>
                        </tr>

                        {{-- Expandable Detail Row --}}
                        <tr x-show="$store.journal && $store.journal.expandedRows.includes({{ $row['entry_number'] }})" class="bg-blue-50/50 dark:bg-blue-900/10">
                            <td colspan="{{ $detailColSpan }}" class="px-4 py-2 border border-gray-300 dark:border-gray-700">
                                <div class="flex items-start justify-between gap-4 text-xs">
                                    <div>
                                        <strong>Pilns apraksts:</strong> {{ $row['description'] }}
                                    </div>
                                    <div>
                                        <strong>Bankas info:</strong> {{ $row['partner'] }} ({{ $row['document_details'] }})
                                    </div>
                                    @if($row['transaction_id'])
                                    <div class="shrink-0"
                                         @click.stop
                                         wire:click="mountTransactionModal({{ $row['transaction_id'] }})">
                                        <x-filament::button size="xs" color="gray" icon="heroicon-o-pencil">
                                            Rediģēt
                                        </x-filament::button>
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                    @endforeach

                    {{-- Closing Balances Row --}}
                    <tr class="bg-yellow-100 dark:bg-yellow-900/20 font-bold text-gray-800 dark:text-gray-200 border-t-2 border-gray-400">
                        <td colspan="7" class="px-2 py-2 border border-gray-300 dark:border-gray-700 text-right">Beigu atlikums:</td>
                        <td class="border border-gray-300 dark:border-gray-700"></td>
                        @foreach($accounts as $acc)
                            <td colspan="2" class="border border-gray-300 dark:border-gray-700"></td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right {{ ($closing_balances[$acc->id] ?? 0) < 0 ? 'text-red-600' : '' }}">
                                {{ number_format($closing_balances[$acc->id] ?? 0, 2, ',', ' ') }}
                            </td>
                        @endforeach
                        <td colspan="{{ $totalAnalysisCols }}" class="border border-gray-300 dark:border-gray-700"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
