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
            <div class="text-center mb-4">
                <h2 class="text-2xl font-bold">
                    {{ $selectedYear }}. GADS
                </h2>
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
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <table class="fi-ta-table w-full text-start divide-y divide-gray-200 dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-start text-sm font-medium text-gray-950 dark:text-white">Mēnesis</th>
                        <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white">Ieņēmumi (EUR)</th>
                        <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white">Izdevumi (EUR)</th>
                        <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white">Bilance (EUR)</th>
                        <th class="px-4 py-3 text-end text-sm font-medium text-gray-950 dark:text-white w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach($monthlySummary as $summary)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">
                                {{ $summary['month'] }}
                            </td>
                            <td class="px-4 py-3 text-sm text-end text-gray-950 dark:text-white">
                                {{ number_format($summary['income'], 2, ',', ' ') }} €
                            </td>
                            <td class="px-4 py-3 text-sm text-end text-gray-950 dark:text-white">
                                {{ number_format($summary['expense'], 2, ',', ' ') }} €
                            </td>
                            <td class="px-4 py-3 text-sm text-end font-medium {{ $summary['balance'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ number_format($summary['balance'], 2, ',', ' ') }} €
                            </td>
                            <td class="px-4 py-3 text-sm text-end">
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    icon="heroicon-o-eye"
                                    wire:click="viewMonthDetails({{ $summary['month_number'] }})"
                                >
                                    Skatīt
                                </x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                    
                    {{-- Total Row --}}
                    <tr class="bg-gray-50 dark:bg-white/5 font-bold">
                        <td class="px-4 py-3 text-sm text-gray-950 dark:text-white">GADA KOPĀ</td>
                        <td class="px-4 py-3 text-sm text-end text-success-600 dark:text-success-400">
                            {{ number_format(collect($monthlySummary)->sum('income'), 2, ',', ' ') }} €
                        </td>
                        <td class="px-4 py-3 text-sm text-end text-danger-600 dark:text-danger-400">
                            {{ number_format(collect($monthlySummary)->sum('expense'), 2, ',', ' ') }} €
                        </td>
                        <td class="px-4 py-3 text-sm text-end {{ collect($monthlySummary)->last()['balance'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ number_format(collect($monthlySummary)->last()['balance'] ?? 0, 2, ',', ' ') }} €
                        </td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

    @else
        {{-- Month Detail View --}}
        <div class="mb-6">
            <div class="text-center mb-4">
                <h2 class="text-2xl font-bold">
                    {{ strtoupper($this->getTitle()) }}
                </h2>
            </div>

            {{-- Month Summary Cards --}}
            @php
                $monthData = collect($monthlySummary)->firstWhere('month_number', $selectedMonth);
            @endphp

            @if($monthData)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <x-filament::card>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Ieņēmumi
                        </div>
                        <div class="text-2xl font-bold text-success-600 dark:text-success-400 mt-2">
                            {{ number_format($monthData['income'], 2, ',', ' ') }} EUR
                        </div>
                    </x-filament::card>

                    <x-filament::card>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Izdevumi
                        </div>
                        <div class="text-2xl font-bold text-danger-600 dark:text-danger-400 mt-2">
                            {{ number_format($monthData['expense'], 2, ',', ' ') }} EUR
                        </div>
                    </x-filament::card>

                    <x-filament::card>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Bilance (Mēneša beigās)
                        </div>
                        <div class="text-2xl font-bold {{ $monthData['balance'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }} mt-2">
                            {{ number_format($monthData['balance'], 2, ',', ' ') }} EUR
                        </div>
                    </x-filament::card>
                </div>
            @endif
        </div>

        {{-- Detailed Transactions Table --}}
        {{ $this->table }}
    @endif
</x-filament-panels::page>
