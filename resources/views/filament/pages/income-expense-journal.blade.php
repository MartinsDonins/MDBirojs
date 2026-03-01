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
                    
                    @if($selectedYear && !$selectedMonth)
                       {{-- Year view actions if needed --}}
                    @endif
                </div>
            </div>

            {{-- Month Navigation --}}
            @if($selectedMonth)
                <div class="mb-4 flex items-center justify-between">
                    <button wire:click="viewMonthDetails({{ $selectedMonth - 1 }})" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        &larr; Iepriekšējais
                    </button>
                    <span class="font-bold text-lg text-gray-800 dark:text-gray-200">
                        {{ \Carbon\Carbon::create()->month($selectedMonth)->locale('lv')->monthName }} {{ $selectedYear }}
                    </span>
                    <button wire:click="viewMonthDetails({{ $selectedMonth + 1 }})" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Nākamais &rarr;
                    </button>
                </div>
            @endif

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
            <div class="flex justify-between items-center mb-4">
                <x-filament::button color="gray" wire:click="backToYearSummary">
                    &larr; Atpakaļ uz gadu sarakstu
                </x-filament::button>
                <div class="flex gap-2">
                    <x-filament::button
                        wire:click="toggleInvalidFilter"
                        color="{{ $showOnlyInvalid ? 'danger' : 'gray' }}"
                        icon="{{ $showOnlyInvalid ? 'heroicon-o-x-circle' : 'heroicon-o-funnel' }}"
                        title="Filtrēt rindas bez analīzes kartēšanas">
                        {{ $showOnlyInvalid ? 'Rādīt visus' : 'Nekartētie' }}
                    </x-filament::button>
                    <x-filament::button wire:click="mountAction('createTransaction')">
                        Pievienot darījumu
                    </x-filament::button>
                </div>
            </div>

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

        {{-- Dynamic Account Journal Table --}}
        <div class="overflow-x-auto bg-white dark:bg-gray-900 p-4 rounded-lg shadow-sm"
             x-data="{}"
             x-init="if (!Alpine.store('journal')) { Alpine.store('journal', { expandedRows: [] }); }">
            <table class="w-full border-collapse border border-gray-300 dark:border-gray-700 text-xs">
                <thead>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom sticky left-0 bg-gray-100 dark:bg-gray-800 z-10 text-gray-900 dark:text-gray-100" style="min-width: 40px;">Nr.</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom sticky left-8 bg-gray-100 dark:bg-gray-800 z-10 text-gray-900 dark:text-gray-100" style="min-width: 65px;">Datums</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 100px;">Dok. nr.<br>un datums</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 120px;">Partneris</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 150px;">Apraksts</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 80px;">Kategorija</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100">Sasaite</th>
                        <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom text-gray-900 dark:text-gray-100" style="min-width: 40px;">Statuss</th>

                        {{-- 2. Konti (Atlikums) --}}
                        @foreach($accounts as $acc)
                            <th colspan="3" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/30 text-gray-900 dark:text-gray-100">{{ $acc->name }}</th>
                        @endforeach

                        {{-- 3. Ieņēmumu Analīze --}}
                        <th colspan="4" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-green-50 dark:bg-green-900/30 text-gray-900 dark:text-gray-100">Ieņēmumi (EUR)</th>

                        {{-- 4. Izdevumu Analīze --}}
                        <th colspan="5" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-red-50 dark:bg-red-900/30 text-gray-900 dark:text-gray-100">Izdevumi (EUR)</th>

                        {{-- 5. Atbilstība --}}
                        <th rowspan="3" class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-center text-gray-900 dark:text-gray-100" title="Atbilstība: vai darījuma summa pilnībā iekļaujas analīzes kolonnās">Atb.</th>
                    </tr>
                    <tr class="bg-gray-50 dark:bg-gray-800/50 text-center text-[10px]">
                        {{-- Kontu apakškolonnas --}}
                        @foreach($accounts as $acc)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-green-600 dark:text-green-400 bg-gray-100 dark:bg-gray-800" title="Ieņēmumi">Ieņ.</th>
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-red-600 dark:text-red-400 bg-gray-100 dark:bg-gray-800" title="Izdevumi">Izd.</th>
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Atlikums">Atlikums</th>
                        @endforeach

                        {{-- Ieņēmumu apakškolonnas --}}
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Saimnieciskās darbības ieņēmumi (kol.4/5/6)">Saimn.<br>darb.</th>
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Neapliekamie ieņēmumi (kol.10)">Neapl.<br>(kol.10)</th>
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Nav attiecināmi uz nodokli (kol.8)">Nav attiec.<br>(kol.8)</th>
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">Kopā</th>

                        {{-- Izdevumu apakškolonnas --}}
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Saistīti ar saimniecisko darbību (kol.19-23)">Saistīti<br>ar SD</th>
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Proporcionāli sadalāmie">Prop.<br>sadal.</th>
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Nesaistīti ar SD (kol.18)">Nesaist.<br>(kol.18)</th>
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100" title="Nav attiecināms uz nodokli (kol.16)">Nav attiec.<br>(kol.16)</th>
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
                        
                        {{-- Income Analysis Cols (4) --}}
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        
                        {{-- Expense Analysis Cols (5) --}}
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                        <th class="border border-gray-300 dark:border-gray-700">{{ $colNum++ }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900">
                    {{-- Opening Balances Row --}}
                    <tr class="bg-yellow-50 dark:bg-yellow-900/10 font-bold text-gray-700 dark:text-gray-300">
                        <td colspan="7" class="px-2 py-2 border border-gray-300 dark:border-gray-700 text-right text-xs">Sākuma atlikums:</td>
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
                        <td colspan="10" class="border border-gray-300 dark:border-gray-700"></td>
                    </tr>

                    @foreach($rows as $row)
                    @if(!$showOnlyInvalid || !$row['is_mapped'])
                        <tr wire:key="row-{{ $row['entry_number'] }}" class="group hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer" 
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

                            {{-- Statuss (Interactive) --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-center cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                                title="Klikšķiniet, lai mainītu statusu">
                                @if($row['transaction_id'])
                                <div class="w-full h-full"
                                     @click.stop
                                     wire:click="mountStatusModal({{ $row['transaction_id'] }})">
                                    @if($row['status'] === 'COMPLETED')
                                        <span class="text-green-600 dark:text-green-400 text-lg" title="Apstiprināts">✓</span>
                                    @elseif($row['status'] === 'NEEDS_REVIEW')
                                        <span class="text-orange-500 text-lg" title="Nepieciešama pārbaude">?</span>
                                    @else
                                        <span class="text-gray-400 text-lg" title="Melnraksts">•</span>
                                    @endif
                                </div>
                                @endif
                            </td>

                            {{-- 2. Konti --}}
                            @foreach($accounts as $acc)
                                {{-- Ieņēmumi (Ja šis konts un IN) --}}
                                <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-green-600 dark:text-green-400 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20">
                                    @if($row['transaction_account_id'] == $acc->id && $row['transaction_type'] == 'INCOME')
                                        {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                    @endif
                                </td>
                                {{-- Izdevumi (Ja šis konts un EXP) --}}
                                <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-red-600 dark:text-red-400 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20">
                                    @if($row['transaction_account_id'] == $acc->id && $row['transaction_type'] == 'EXPENSE')
                                        {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                    @endif
                                </td>
                                {{-- Running Balance --}}
                                <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right font-bold bg-gray-50 dark:bg-gray-800 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 {{ ($row['account_balances'][$acc->id] ?? 0) < 0 ? 'text-red-600' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ number_format($row['account_balances'][$acc->id] ?? 0, 2, ',', ' ') }}
                                </td>
                            @endforeach

                            {{-- 3. Ieņēmumu Analīze (Logic mapping) --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="Kol.4/5/6 — Saimn. darb.">
                                @if($row['transaction_type'] == 'INCOME' && in_array($row['category_vid_column'], [4,5,6]))
                                    {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                @endif
                            </td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="Kol.10 — Neapliekamie">
                                @if($row['transaction_type'] == 'INCOME' && $row['category_vid_column'] == 10)
                                    {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                @endif
                            </td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="Kol.8 — Nav attiecināms uz nodokli">
                                @if($row['transaction_type'] == 'INCOME' && $row['category_vid_column'] == 8)
                                    {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                @endif
                            </td>
                            {{-- Ieņēmumi Kopā — tikai kartētās summas --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right font-bold text-green-600 dark:text-green-400">
                                @if($row['transaction_type'] == 'INCOME' && $row['is_mapped'])
                                    {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                @endif
                            </td>

                            {{-- 4. Izdevumu Analīze (Logic mapping) --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="Kol.19-23 — Saistīti ar SD">
                                @if($row['transaction_type'] == 'EXPENSE' && in_array($row['category_vid_column'], [19,20,21,22,23]))
                                    {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                @endif
                            </td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100">
                                {{-- Prop. sadal. (nav kartēts) --}}
                            </td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="Kol.18 — Nesaistīti ar SD">
                                @if($row['transaction_type'] == 'EXPENSE' && $row['category_vid_column'] == 18)
                                    {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                @endif
                            </td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="Kol.16 — Nav attiecināms uz nodokli">
                                @if($row['transaction_type'] == 'EXPENSE' && $row['category_vid_column'] == 16)
                                    {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                @endif
                            </td>
                            {{-- Izdevumi Kopā — tikai kartētās summas --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right font-bold text-red-600 dark:text-red-400">
                                @if($row['transaction_type'] == 'EXPENSE' && $row['is_mapped'])
                                    {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                @endif
                            </td>

                            {{-- 5. Atbilstības indikators --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-center">
                                @if($row['transaction_type'] === 'INCOME' || $row['transaction_type'] === 'EXPENSE')
                                    @if($row['is_mapped'])
                                        <span class="text-green-600 dark:text-green-400 font-bold text-sm" title="Summa pilnībā iekļauta analīzes kolonnās">✓</span>
                                    @else
                                        <span class="text-red-500 font-bold text-sm" title="Summa nav kartēta uz analīzes kolonnu — pārbaudiet kategoriju!">✗</span>
                                    @endif
                                @else
                                    <span class="text-gray-400 text-xs" title="{{ $row['transaction_type'] }}">—</span>
                                @endif
                            </td>
                        </tr>

                        {{-- Expandable Detail Row --}}
                        <tr x-show="$store.journal && $store.journal.expandedRows.includes({{ $row['entry_number'] }})" class="bg-blue-50/50 dark:bg-blue-900/10">
                            <td colspan="{{ 7 + (count($accounts) * 3) + 10 }}" class="px-4 py-2 border border-gray-300 dark:border-gray-700">
                                <div class="grid grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <strong>Pilns apraksts:</strong> {{ $row['description'] }}
                                    </div>
                                    <div>
                                        <strong>Bankas info:</strong> {{ $row['partner'] }} ({{ $row['document_details'] }})
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                    @endforeach

                    {{-- Closing Balances Row --}}
                    <tr class="bg-yellow-100 dark:bg-yellow-900/20 font-bold text-gray-800 dark:text-gray-200 border-t-2 border-gray-400">
                        <td colspan="7" class="px-2 py-2 border border-gray-300 dark:border-gray-700 text-right">Beigu atlikums:</td>
                        @foreach($accounts as $acc)
                            <td colspan="2" class="border border-gray-300 dark:border-gray-700"></td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right {{ ($closing_balances[$acc->id] ?? 0) < 0 ? 'text-red-600' : '' }}">
                                {{ number_format($closing_balances[$acc->id] ?? 0, 2, ',', ' ') }}
                            </td>
                        @endforeach
                        <td colspan="10" class="border border-gray-300 dark:border-gray-700"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
