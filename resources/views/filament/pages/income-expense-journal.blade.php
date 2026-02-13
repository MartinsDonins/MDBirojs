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

        {{-- VID Format Transactions Table (Full 24 Columns) --}}
        <div class="overflow-x-auto bg-white dark:bg-gray-900 p-4 rounded-lg">
            <table class="w-full border-collapse border-2 border-gray-400 dark:border-gray-600" style="font-size: 10px;">
                <thead>
                    <tr class="bg-orange-200 dark:bg-orange-800">
                        <th rowspan="2" class="px-1 py-2 border border-gray-400 dark:border-gray-600 text-gray-900 dark:text-gray-100 font-semibold" style="min-width: 40px;">Nr.</th>
                        <th rowspan="2" class="px-1 py-2 border border-gray-400 dark:border-gray-600 text-gray-900 dark:text-gray-100 font-semibold" style="min-width: 70px;">Datums</th>
                        <th rowspan="2" class="px-1 py-2 border border-gray-400 dark:border-gray-600 text-gray-900 dark:text-gray-100 font-semibold" style="min-width: 100px;">Dok. nr. (rēķ., pers. vārds)</th>
                        <th rowspan="2" class="px-1 py-2 border border-gray-400 dark:border-gray-600 text-gray-900 dark:text-gray-100 font-semibold" style="min-width: 150px;">Saimn. darbība</th>
                        <th colspan="8" class="px-1 py-2 border border-gray-400 dark:border-gray-600 bg-green-100 dark:bg-green-900 text-gray-900 dark:text-gray-100 font-bold">Ieņēmumi, euro</th>
                        <th colspan="13" class="px-1 py-2 border border-gray-400 dark:border-gray-600 bg-red-100 dark:bg-red-900 text-gray-900 dark:text-gray-100 font-bold">Izdevumi, euro</th>
                    </tr>
                    <tr class="bg-orange-100 dark:bg-orange-700">
                        {{-- Ieņēmumi kolonnas --}}
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-green-50 dark:bg-green-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Kase</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-green-50 dark:bg-green-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Maks. konts</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-green-50 dark:bg-green-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Citi maks.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-green-50 dark:bg-green-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Kopā</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-green-50 dark:bg-green-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Nav attiec.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-green-50 dark:bg-green-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Subsīd.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-green-50 dark:bg-green-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Neapl.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-green-50 dark:bg-green-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Nesaist.</th>
                        {{-- Izdevumi kolonnas --}}
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Kase</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Maks. konts</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Citi maks.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Kopā</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Nav attiec.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Subsīd.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Nav saist.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Preces</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Pakalpoj.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Pamatl.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Nemat.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Darba sam.</th>
                        <th class="px-1 py-1 border border-gray-400 dark:border-gray-600 bg-red-50 dark:bg-red-800 text-gray-900 dark:text-gray-100" style="min-width: 50px;">Citi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900">
                    @foreach($vidMonthDetail as $row)
                        <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-900 dark:text-gray-100">
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-center">{{ $row['entry_number'] }}</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700">{{ $row['account'] }}</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700">
                                <div>{{ $row['description'] }}</div>
                                @if($row['category'])
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $row['category'] }}</div>
                                @endif
                            </td>
                            {{-- Ieņēmumi kolonnas 4-11 --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 4){{ number_format($row['amount'], 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 5){{ number_format($row['amount'], 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 6){{ number_format($row['amount'], 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 7){{ number_format($row['amount'], 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 8){{ number_format($row['amount'], 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 9){{ number_format($row['amount'], 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 10){{ number_format($row['amount'], 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 11){{ number_format($row['amount'], 2, ',', ' ') }}@endif</td>
                            {{-- Izdevumi kolonnas 12-24 --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 12){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 13){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 14){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 15){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 16){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 17){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 18){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 19){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 20){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 21){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 22){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 23){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right">@if($row['vid_column'] == 24){{ number_format(abs($row['amount']), 2, ',', ' ') }}@endif</td>
                        </tr>
                    @endforeach
                    
                    {{-- Monthly Totals --}}
                    <tr class="bg-gray-200 dark:bg-gray-700 font-bold text-gray-900 dark:text-gray-100">
                        <td colspan="4" class="px-1 py-2 border border-gray-400 dark:border-gray-600 text-right">KOPĀ:</td>
                        @for($col = 4; $col <= 24; $col++)
                            <td class="px-1 py-1 border border-gray-400 dark:border-gray-600 text-right">
                                {{ number_format(collect($vidMonthDetail)->where('vid_column', $col)->sum(fn($r) => $col >= 12 ? abs($r['amount']) : $r['amount']), 2, ',', ' ') }}
                            </td>
                        @endfor
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
