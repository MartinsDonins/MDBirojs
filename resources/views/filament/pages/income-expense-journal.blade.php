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

            @php
                $incomeColCount  = count($journalIncomeColumns);
                $expenseColCount = count($journalExpenseColumns);
                $yearsTotalCols  = 2 + count($accounts) * 3 + 1 + ($incomeColCount + 1) + ($expenseColCount + 1) + 1;
            @endphp
            <div x-data="{ expanded: null }" class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 dark:border-gray-700 text-xs">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th rowspan="2" class="px-2 py-2 border border-gray-300 dark:border-gray-700 align-bottom w-16"></th>
                            <th rowspan="2" class="px-3 py-2 border border-gray-300 dark:border-gray-700 text-start text-sm font-medium text-gray-950 dark:text-white align-bottom whitespace-nowrap" style="min-width:110px">Gads</th>
                            {{-- Per-account group headers (3 sub-cols each) --}}
                            @foreach($accounts as $acc)
                            <th colspan="3" class="px-1 py-2 border border-gray-300 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/30 text-center text-sm font-medium text-gray-950 dark:text-white" title="{{ $acc->name }}">{{ mb_substr($acc->name, 0, 14) }}</th>
                            @endforeach
                            <th rowspan="2" class="px-3 py-2 border border-gray-300 dark:border-gray-700 text-end text-sm font-medium text-gray-950 dark:text-white align-bottom whitespace-nowrap" style="min-width:100px">Bilance</th>
                            <th colspan="{{ $incomeColCount + 1 }}" class="px-1 py-2 border border-gray-300 dark:border-gray-700 bg-green-50 dark:bg-green-900/30 text-center text-sm font-medium text-gray-950 dark:text-white">Ieņēmumi (EUR)</th>
                            <th colspan="{{ $expenseColCount + 1 }}" class="px-1 py-2 border border-gray-300 dark:border-gray-700 bg-red-50 dark:bg-red-900/30 text-center text-sm font-medium text-gray-950 dark:text-white">Izdevumi (EUR)</th>
                            <th rowspan="2" class="px-3 py-2 border border-gray-300 dark:border-gray-700 text-end text-sm font-medium text-gray-950 dark:text-white align-bottom whitespace-nowrap" style="min-width:100px">Rezultāts</th>
                        </tr>
                        <tr class="bg-gray-100 dark:bg-gray-800 text-center text-[10px]">
                            @foreach($accounts as $acc)
                                <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-green-600 dark:text-green-400 bg-gray-100 dark:bg-gray-800 whitespace-nowrap" style="min-width:85px" title="Ieņēmumi">Ieņ.</th>
                                <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-red-600 dark:text-red-400 bg-gray-100 dark:bg-gray-800 whitespace-nowrap" style="min-width:85px" title="Izdevumi">Izd.</th>
                                <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 whitespace-nowrap" style="min-width:90px" title="Atlikums">Atlikums</th>
                            @endforeach
                            {{-- Income analysis sub-headers --}}
                            @foreach($journalIncomeColumns as $col)
                                <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 whitespace-nowrap bg-gray-100 dark:bg-gray-800" style="min-width:75px" title="{{ $col['name'] }}">{{ $col['abbr'] }}</th>
                            @endforeach
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-green-100 dark:bg-green-900/20 text-gray-900 dark:text-gray-100 whitespace-nowrap" style="min-width:80px">Kopā</th>
                            {{-- Expense analysis sub-headers --}}
                            @foreach($journalExpenseColumns as $col)
                                <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 whitespace-nowrap bg-gray-100 dark:bg-gray-800" style="min-width:75px" title="{{ $col['name'] }}">{{ $col['abbr'] }}</th>
                            @endforeach
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-red-100 dark:bg-red-900/20 text-gray-900 dark:text-gray-100 whitespace-nowrap" style="min-width:80px">Kopā</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach($yearlySummary as $yearData)
                            @php
                                $yIncomeUncat  = max(0, $yearData['income']  - $yearData['income_kopaa']);
                                $yExpenseUncat = max(0, $yearData['expense'] - $yearData['expense_kopaa']);
                            @endphp
                            {{-- Main year row --}}
                            <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer"
                                @click="expanded = (expanded === {{ $yearData['year'] }}) ? null : {{ $yearData['year'] }}">
                                {{-- Action button — first column --}}
                                <td class="px-2 py-2 text-center border border-gray-300 dark:border-gray-700" @click.stop>
                                    <div class="flex flex-col items-center gap-1">
                                        <x-filament::button
                                            size="sm"
                                            icon="heroicon-o-eye"
                                            wire:click="selectYear({{ $yearData['year'] }})"
                                        >
                                            Atvērt
                                        </x-filament::button>
                                        <button class="text-gray-400 hover:text-primary-500 transition-colors text-[10px] flex items-center gap-0.5"
                                            @click.stop="expanded = (expanded === {{ $yearData['year'] }}) ? null : {{ $yearData['year'] }}">
                                            <span x-text="expanded === {{ $yearData['year'] }} ? '▲ Aizvērt' : '▼ Analīze'"></span>
                                        </button>
                                    </div>
                                </td>
                                {{-- Year + status badges --}}
                                <td class="px-3 py-3 text-sm font-bold text-gray-950 dark:text-white border border-gray-300 dark:border-gray-700 whitespace-nowrap">
                                    {{ $yearData['year'] }}
                                    @if($yearData['tx_total'] > 0)
                                        @if($yearData['all_completed'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400" title="Visi {{ $yearData['tx_total'] }} darījumi apstiprināti">✓</span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400" title="{{ $yearData['tx_completed'] }}/{{ $yearData['tx_total'] }} apstiprināti">{{ $yearData['tx_completed'] }}/{{ $yearData['tx_total'] }}</span>
                                        @endif
                                        @if($yearData['columns_ok'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400" title="Žurnāla ailes aizpildītas">OK</span>
                                        @endif
                                    @endif
                                </td>
                                {{-- Per-account: Ieņ. | Izd. | Atlikums --}}
                                @foreach($accounts as $acc)
                                    <td class="px-2 py-3 text-xs text-end border border-gray-300 dark:border-gray-700 text-success-700 dark:text-success-400 whitespace-nowrap">
                                        @if(($yearData['account_income'][$acc->id] ?? 0) > 0){{ number_format($yearData['account_income'][$acc->id], 2, ',', ' ') }}@endif
                                    </td>
                                    <td class="px-2 py-3 text-xs text-end border border-gray-300 dark:border-gray-700 text-danger-700 dark:text-danger-400 whitespace-nowrap">
                                        @if(($yearData['account_expense'][$acc->id] ?? 0) > 0){{ number_format($yearData['account_expense'][$acc->id], 2, ',', ' ') }}@endif
                                    </td>
                                    <td class="px-2 py-3 text-xs text-end font-medium border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 whitespace-nowrap {{ ($yearData['account_balances'][$acc->id] ?? 0) < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-gray-100' }}">
                                        {{ number_format($yearData['account_balances'][$acc->id] ?? 0, 2, ',', ' ') }}
                                    </td>
                                @endforeach
                                <td class="px-3 py-3 text-sm text-end font-bold whitespace-nowrap border border-gray-300 dark:border-gray-700 {{ $yearData['end_balance'] >= 0 ? 'text-gray-950 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ number_format($yearData['end_balance'], 2, ',', ' ') }} €
                                </td>
                                @foreach($journalIncomeColumns as $i => $col)
                                <td class="px-2 py-3 text-xs text-end border border-gray-300 dark:border-gray-700 text-success-700 dark:text-success-400 whitespace-nowrap">
                                    @if(($yearData['income_cols'][$i] ?? 0) > 0){{ number_format($yearData['income_cols'][$i], 2, ',', ' ') }}@endif
                                </td>
                                @endforeach
                                <td class="px-2 py-3 text-xs text-end font-bold border border-gray-300 dark:border-gray-700 bg-green-50/60 dark:bg-green-900/10 text-success-700 dark:text-success-400 whitespace-nowrap">
                                    @if($yearData['income_kopaa'] > 0){{ number_format($yearData['income_kopaa'], 2, ',', ' ') }}@endif
                                </td>
                                @foreach($journalExpenseColumns as $i => $col)
                                <td class="px-2 py-3 text-xs text-end border border-gray-300 dark:border-gray-700 text-danger-700 dark:text-danger-400 whitespace-nowrap">
                                    @if(($yearData['expense_cols'][$i] ?? 0) > 0){{ number_format($yearData['expense_cols'][$i], 2, ',', ' ') }}@endif
                                </td>
                                @endforeach
                                <td class="px-2 py-3 text-xs text-end font-bold border border-gray-300 dark:border-gray-700 bg-red-50/60 dark:bg-red-900/10 text-danger-700 dark:text-danger-400 whitespace-nowrap">
                                    @if($yearData['expense_kopaa'] > 0){{ number_format($yearData['expense_kopaa'], 2, ',', ' ') }}@endif
                                </td>
                                <td class="px-3 py-3 text-sm text-end font-medium whitespace-nowrap border border-gray-300 dark:border-gray-700 {{ $yearData['result'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                    {{ number_format($yearData['result'], 2, ',', ' ') }} €
                                </td>
                            </tr>

                            {{-- Expandable analysis panel row --}}
                            <tr x-show="expanded === {{ $yearData['year'] }}" x-cloak>
                                <td colspan="{{ $yearsTotalCols }}" class="p-0 border-x border-b border-gray-300 dark:border-gray-700">
                                    <div class="bg-gray-100 dark:bg-gray-800">
                                        {{-- Panel title --}}
                                        <div class="px-4 py-2 border-b border-gray-200 dark:border-white/10 bg-gray-200 dark:bg-gray-900/80">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wide">{{ $yearData['year'] }}. gada žurnāla analīze</span>
                                        </div>
                                        <div class="flex divide-x divide-gray-200 dark:divide-white/10">

                                            {{-- INCOME --}}
                                            <div class="flex-1 min-w-0">
                                                <div class="px-3 py-1 bg-green-50 dark:bg-green-900/20 flex justify-between items-center border-b border-green-100 dark:border-green-900/40">
                                                    <span class="text-[11px] font-semibold text-green-800 dark:text-green-300 uppercase tracking-wide">Ieņēmumi</span>
                                                    <span class="text-xs font-bold text-green-700 dark:text-green-400">{{ number_format($yearData['income'], 2, ',', ' ') }}</span>
                                                </div>
                                                <table class="w-full">
                                                    @foreach($journalIncomeColumns as $i => $col)
                                                        @php $ct = $yearData['income_cols'][$i] ?? 0; @endphp
                                                        <tr class="{{ $ct > 0 ? 'bg-white/50 dark:bg-white/5' : 'opacity-40 bg-white/30 dark:bg-white/0' }} border-b border-gray-200 dark:border-white/5">
                                                            <td class="px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-300 truncate max-w-[1px]" title="{{ $col['name'] }}">
                                                                <span class="font-mono text-gray-500 dark:text-gray-500">{{ $col['abbr'] }}</span>
                                                                <span class="ml-1">{{ $col['name'] }}</span>
                                                            </td>
                                                            <td class="px-2 py-0.5 text-[11px] text-right font-medium text-green-700 dark:text-green-400 whitespace-nowrap w-28">{{ $ct > 0 ? number_format($ct, 2, ',', ' ') : '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                    @if($yIncomeUncat > 0.005)
                                                        <tr class="border-b border-orange-200 dark:border-orange-900/30 bg-orange-100/60 dark:bg-orange-900/10">
                                                            <td class="px-2 py-0.5 text-[11px] text-orange-700 dark:text-orange-400 italic">Nav kartēti</td>
                                                            <td class="px-2 py-0.5 text-[11px] text-right font-medium text-orange-700 dark:text-orange-400 w-28">{{ number_format($yIncomeUncat, 2, ',', ' ') }}</td>
                                                        </tr>
                                                    @endif
                                                    <tr class="bg-green-100 dark:bg-green-900/20">
                                                        <td class="px-2 py-1 text-[11px] font-semibold text-green-900 dark:text-green-300">Kopā kartēti</td>
                                                        <td class="px-2 py-1 text-[11px] text-right font-bold text-green-800 dark:text-green-400 w-28">{{ number_format($yearData['income_kopaa'], 2, ',', ' ') }}</td>
                                                    </tr>
                                                </table>
                                            </div>

                                            {{-- EXPENSE --}}
                                            <div class="flex-1 min-w-0">
                                                <div class="px-3 py-1 bg-red-100 dark:bg-red-900/20 flex justify-between items-center border-b border-red-200 dark:border-red-900/40">
                                                    <span class="text-[11px] font-semibold text-red-900 dark:text-red-300 uppercase tracking-wide">Izdevumi</span>
                                                    <span class="text-xs font-bold text-red-800 dark:text-red-400">{{ number_format($yearData['expense'], 2, ',', ' ') }}</span>
                                                </div>
                                                <table class="w-full">
                                                    @foreach($journalExpenseColumns as $i => $col)
                                                        @php $ct = $yearData['expense_cols'][$i] ?? 0; @endphp
                                                        <tr class="{{ $ct > 0 ? 'bg-white/50 dark:bg-white/5' : 'opacity-40 bg-white/30 dark:bg-white/0' }} border-b border-gray-200 dark:border-white/5">
                                                            <td class="px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-300 truncate max-w-[1px]" title="{{ $col['name'] }}">
                                                                <span class="font-mono text-gray-500 dark:text-gray-500">{{ $col['abbr'] }}</span>
                                                                <span class="ml-1">{{ $col['name'] }}</span>
                                                            </td>
                                                            <td class="px-2 py-0.5 text-[11px] text-right font-medium text-red-700 dark:text-red-400 whitespace-nowrap w-28">{{ $ct > 0 ? number_format($ct, 2, ',', ' ') : '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                    @if($yExpenseUncat > 0.005)
                                                        <tr class="border-b border-orange-200 dark:border-orange-900/30 bg-orange-100/60 dark:bg-orange-900/10">
                                                            <td class="px-2 py-0.5 text-[11px] text-orange-700 dark:text-orange-400 italic">Nav kartēti</td>
                                                            <td class="px-2 py-0.5 text-[11px] text-right font-medium text-orange-700 dark:text-orange-400 w-28">{{ number_format($yExpenseUncat, 2, ',', ' ') }}</td>
                                                        </tr>
                                                    @endif
                                                    <tr class="bg-red-100 dark:bg-red-900/20">
                                                        <td class="px-2 py-1 text-[11px] font-semibold text-red-900 dark:text-red-300">Kopā kartēti</td>
                                                        <td class="px-2 py-1 text-[11px] text-right font-bold text-red-800 dark:text-red-400 w-28">{{ number_format($yearData['expense_kopaa'], 2, ',', ' ') }}</td>
                                                    </tr>
                                                </table>
                                            </div>

                                            {{-- BALANCE summary --}}
                                            <div class="w-52 shrink-0">
                                                <div class="px-3 py-1 bg-gray-200 dark:bg-gray-800 border-b border-gray-300 dark:border-white/10">
                                                    <span class="text-[11px] font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Bilance</span>
                                                </div>
                                                <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5">
                                                    <span class="text-gray-800 dark:text-gray-200">Ieņēmumi</span>
                                                    <span class="text-green-700 dark:text-green-400 font-medium">+{{ number_format($yearData['income'], 2, ',', ' ') }}</span>
                                                </div>
                                                <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5">
                                                    <span class="text-gray-700 dark:text-gray-200">Izdevumi</span>
                                                    <span class="text-red-700 dark:text-red-400 font-medium">−{{ number_format($yearData['expense'], 2, ',', ' ') }}</span>
                                                </div>
                                                <div class="px-3 py-1 flex justify-between border-b-2 border-gray-400 dark:border-gray-600 bg-gray-200 dark:bg-gray-800">
                                                    <span class="text-[11px] font-bold text-gray-900 dark:text-white">Rezultāts</span>
                                                    <span class="text-xs font-bold {{ $yearData['result'] >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">{{ ($yearData['result'] >= 0 ? '+' : '') . number_format($yearData['result'], 2, ',', ' ') }}</span>
                                                </div>
                                                <div class="px-3 pt-1.5 pb-0.5">
                                                    <span class="text-[10px] uppercase tracking-wide text-gray-700 dark:text-gray-300">Kontu atlikumi (gada beigas)</span>
                                                </div>
                                                @foreach($accounts as $acc)
                                                    @php $bal = $yearData['account_balances'][$acc->id] ?? 0; @endphp
                                                    <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5 last:border-0">
                                                        <span class="text-gray-800 dark:text-gray-200 truncate mr-1" title="{{ $acc->name }}">{{ mb_substr($acc->name, 0, 16) }}</span>
                                                        <span class="font-medium whitespace-nowrap {{ $bal < 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($bal, 2, ',', ' ') }}</span>
                                                    </div>
                                                @endforeach
                                            </div>

                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    {{-- TOTALS footer --}}
                    @php
                        $grandTxTotal     = collect($yearlySummary)->sum('tx_total');
                        $grandTxCompleted = collect($yearlySummary)->sum('tx_completed');
                        $grandTxPending   = $grandTxTotal - $grandTxCompleted;
                    @endphp
                    @if($grandTxTotal > 0)
                    <tfoot>
                        <tr class="font-semibold border-t-2 border-gray-400 dark:border-gray-500">
                            <td class="px-2 py-2 border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700"></td>
                            <td class="px-3 py-2 border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700 text-xs font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                KOPĀ
                                <div class="flex flex-wrap gap-1 mt-0.5">
                                    @if($grandTxPending === 0)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-green-200 text-green-800 dark:bg-green-900/60 dark:text-green-300" title="Visi darījumi apstiprināti">✓ Visi apstiprināti</span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-amber-200 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300" title="{{ $grandTxCompleted }}/{{ $grandTxTotal }} apstiprināti">{{ $grandTxCompleted }}/{{ $grandTxTotal }} apst.</span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-red-200 text-red-800 dark:bg-red-900/60 dark:text-red-300" title="Vēl jāapstiprina">{{ $grandTxPending }} neapst.</span>
                                    @endif
                                </div>
                            </td>
                            {{-- Empty cells for per-account columns --}}
                            @foreach($accounts as $acc)
                                <td class="border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700"></td>
                                <td class="border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700"></td>
                                <td class="border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700"></td>
                            @endforeach
                            {{-- Grand totals: balance, income breakdown, expense breakdown, result --}}
                            <td class="border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700"></td>
                            @foreach($journalIncomeColumns as $i => $col)
                            <td class="px-2 py-2 text-xs text-end font-semibold whitespace-nowrap border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700 text-green-900 dark:text-green-300">
                                {{ number_format(collect($yearlySummary)->sum(fn ($y) => $y['income_cols'][$i] ?? 0), 2, ',', ' ') }}
                            </td>
                            @endforeach
                            <td class="px-2 py-2 text-sm text-end font-bold whitespace-nowrap border border-gray-300 dark:border-gray-700 bg-green-200 dark:bg-green-900/30 text-green-900 dark:text-green-300">
                                {{ number_format(collect($yearlySummary)->sum('income_kopaa'), 2, ',', ' ') }}
                            </td>
                            @foreach($journalExpenseColumns as $i => $col)
                            <td class="px-2 py-2 text-xs text-end font-semibold whitespace-nowrap border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700 text-red-900 dark:text-red-300">
                                {{ number_format(collect($yearlySummary)->sum(fn ($y) => $y['expense_cols'][$i] ?? 0), 2, ',', ' ') }}
                            </td>
                            @endforeach
                            <td class="px-2 py-2 text-sm text-end font-bold whitespace-nowrap border border-gray-300 dark:border-gray-700 bg-red-200 dark:bg-red-900/30 text-red-900 dark:text-red-300">
                                {{ number_format(collect($yearlySummary)->sum('expense_kopaa'), 2, ',', ' ') }}
                            </td>
                            @php $grandResult = collect($yearlySummary)->sum('income') - collect($yearlySummary)->sum('expense'); @endphp
                            <td class="px-3 py-2 text-sm text-end font-bold whitespace-nowrap border border-gray-300 dark:border-gray-700 bg-gray-200 dark:bg-gray-700 {{ $grandResult >= 0 ? 'text-green-900 dark:text-green-300' : 'text-red-900 dark:text-red-300' }}">
                                {{ number_format($grandResult, 2, ',', ' ') }} €
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

    @elseif($selectedMonth === null)
        {{-- Year Summary View --}}
        <div class="mb-6">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                <div class="flex items-center gap-2">
                    <x-filament::button color="gray" icon="heroicon-o-arrow-left" wire:click="backToAllYears">
                        Visi gadi
                    </x-filament::button>
                    {{-- Year navigation --}}
                    <x-filament::button color="gray" icon="heroicon-o-chevron-left" wire:click="goToPrevYear">
                        Iepriekšējais
                    </x-filament::button>
                    <span class="text-base font-bold text-gray-800 dark:text-gray-200 min-w-[80px] text-center">
                        {{ $selectedYear }}. gads
                    </span>
                    <x-filament::button color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="goToNextYear">
                        Nākošais
                    </x-filament::button>
                </div>
                <div class="flex gap-2">
                    <x-filament::button wire:click="mountAction('createTransaction')" icon="heroicon-o-plus">
                        Pievienot darījumu
                    </x-filament::button>
                    <x-filament::button
                        wire:click="mountAction('clearYearData')"
                        color="danger"
                        icon="heroicon-o-trash">
                        Notīrīt gada datus
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

        {{-- Yearly Income/Expense Analysis Panel (same as month detail panel) --}}
        @php
            $yearIncomeCols  = [];
            $yearExpenseCols = [];
            $yearIncomeKopaa  = 0;
            $yearExpenseKopaa = 0;
            $yearIncomeTotal  = collect($monthlySummary)->sum('income');
            $yearExpenseTotal = collect($monthlySummary)->sum('expense');
            foreach ($journalIncomeColumns as $i => $col) {
                $t = collect($monthlySummary)->sum(fn ($m) => $m['income_cols'][$i] ?? 0);
                $yearIncomeCols[$i]  = $t;
                $yearIncomeKopaa    += $t;
            }
            foreach ($journalExpenseColumns as $i => $col) {
                $t = collect($monthlySummary)->sum(fn ($m) => $m['expense_cols'][$i] ?? 0);
                $yearExpenseCols[$i]  = $t;
                $yearExpenseKopaa    += $t;
            }
            $yearResult               = $yearIncomeTotal - $yearExpenseTotal;
            $yearIncomeUncategorized  = max(0, $yearIncomeTotal  - $yearIncomeKopaa);
            $yearExpenseUncategorized = max(0, $yearExpenseTotal - $yearExpenseKopaa);
            $yearLastAccountBalances  = collect($monthlySummary)->last()['account_balances'] ?? [];
        @endphp

        @if(count($monthlySummary) > 0)
        <div class="mb-4 bg-gray-100 dark:bg-gray-800 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
            <div class="px-4 py-2 bg-gray-200 dark:bg-gray-900/80 border-b border-gray-300 dark:border-white/10">
                <span class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wide">{{ $selectedYear }}. gada žurnāla analīze</span>
            </div>
            <div class="flex divide-x divide-gray-300 dark:divide-white/10">

                {{-- INCOME --}}
                <div class="flex-1 min-w-0">
                    <div class="px-3 py-1 bg-green-100 dark:bg-green-900/20 flex justify-between items-center border-b border-green-200 dark:border-green-900/40">
                        <span class="text-[11px] font-semibold text-green-900 dark:text-green-300 uppercase tracking-wide">Ieņēmumi</span>
                        <span class="text-xs font-bold text-green-800 dark:text-green-400">{{ number_format($yearIncomeTotal, 2, ',', ' ') }}</span>
                    </div>
                    <table class="w-full">
                        @foreach($journalIncomeColumns as $i => $col)
                            @php $ct = $yearIncomeCols[$i] ?? 0; @endphp
                            <tr class="{{ $ct > 0 ? 'bg-white/50 dark:bg-white/5' : 'opacity-40 bg-white/30 dark:bg-white/0' }} border-b border-gray-200 dark:border-white/5">
                                <td class="px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-300 truncate max-w-[1px]" title="{{ $col['name'] }}">
                                    <span class="font-mono text-gray-500 dark:text-gray-500">{{ $col['abbr'] }}</span>
                                    <span class="ml-1">{{ $col['name'] }}</span>
                                </td>
                                <td class="px-2 py-0.5 text-[11px] text-right font-medium text-green-700 dark:text-green-400 whitespace-nowrap w-28">{{ $ct > 0 ? number_format($ct, 2, ',', ' ') : '—' }}</td>
                            </tr>
                        @endforeach
                        @if($yearIncomeUncategorized > 0.005)
                            <tr class="border-b border-orange-200 dark:border-orange-900/30 bg-orange-100/60 dark:bg-orange-900/10">
                                <td class="px-2 py-0.5 text-[11px] text-orange-700 dark:text-orange-400 italic">Nav kartēti</td>
                                <td class="px-2 py-0.5 text-[11px] text-right font-medium text-orange-700 dark:text-orange-400 w-28">{{ number_format($yearIncomeUncategorized, 2, ',', ' ') }}</td>
                            </tr>
                        @endif
                        <tr class="bg-green-100 dark:bg-green-900/20">
                            <td class="px-2 py-1 text-[11px] font-semibold text-green-900 dark:text-green-300">Kopā kartēti</td>
                            <td class="px-2 py-1 text-[11px] text-right font-bold text-green-800 dark:text-green-400 w-28">{{ number_format($yearIncomeKopaa, 2, ',', ' ') }}</td>
                        </tr>
                    </table>
                </div>

                {{-- EXPENSE --}}
                <div class="flex-1 min-w-0">
                    <div class="px-3 py-1 bg-red-100 dark:bg-red-900/20 flex justify-between items-center border-b border-red-200 dark:border-red-900/40">
                        <span class="text-[11px] font-semibold text-red-900 dark:text-red-300 uppercase tracking-wide">Izdevumi</span>
                        <span class="text-xs font-bold text-red-800 dark:text-red-400">{{ number_format($yearExpenseTotal, 2, ',', ' ') }}</span>
                    </div>
                    <table class="w-full">
                        @foreach($journalExpenseColumns as $i => $col)
                            @php $ct = $yearExpenseCols[$i] ?? 0; @endphp
                            <tr class="{{ $ct > 0 ? 'bg-white/50 dark:bg-white/5' : 'opacity-40 bg-white/30 dark:bg-white/0' }} border-b border-gray-200 dark:border-white/5">
                                <td class="px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-300 truncate max-w-[1px]" title="{{ $col['name'] }}">
                                    <span class="font-mono text-gray-500 dark:text-gray-500">{{ $col['abbr'] }}</span>
                                    <span class="ml-1">{{ $col['name'] }}</span>
                                </td>
                                <td class="px-2 py-0.5 text-[11px] text-right font-medium text-red-700 dark:text-red-400 whitespace-nowrap w-28">{{ $ct > 0 ? number_format($ct, 2, ',', ' ') : '—' }}</td>
                            </tr>
                        @endforeach
                        @if($yearExpenseUncategorized > 0.005)
                            <tr class="border-b border-orange-200 dark:border-orange-900/30 bg-orange-100/60 dark:bg-orange-900/10">
                                <td class="px-2 py-0.5 text-[11px] text-orange-700 dark:text-orange-400 italic">Nav kartēti</td>
                                <td class="px-2 py-0.5 text-[11px] text-right font-medium text-orange-700 dark:text-orange-400 w-28">{{ number_format($yearExpenseUncategorized, 2, ',', ' ') }}</td>
                            </tr>
                        @endif
                        <tr class="bg-red-100 dark:bg-red-900/20">
                            <td class="px-2 py-1 text-[11px] font-semibold text-red-900 dark:text-red-300">Kopā kartēti</td>
                            <td class="px-2 py-1 text-[11px] text-right font-bold text-red-800 dark:text-red-400 w-28">{{ number_format($yearExpenseKopaa, 2, ',', ' ') }}</td>
                        </tr>
                    </table>
                </div>

                {{-- BALANCE + ACCOUNTS --}}
                <div class="w-52 shrink-0">
                    <div class="px-3 py-1 bg-gray-200 dark:bg-gray-800 border-b border-gray-300 dark:border-white/10">
                        <span class="text-[11px] font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Bilance</span>
                    </div>
                    <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5">
                        <span class="text-gray-800 dark:text-gray-200">Ieņēmumi</span>
                        <span class="text-green-700 dark:text-green-400 font-medium">+{{ number_format($yearIncomeTotal, 2, ',', ' ') }}</span>
                    </div>
                    <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5">
                        <span class="text-gray-800 dark:text-gray-200">Izdevumi</span>
                        <span class="text-red-700 dark:text-red-400 font-medium">−{{ number_format($yearExpenseTotal, 2, ',', ' ') }}</span>
                    </div>
                    <div class="px-3 py-1 flex justify-between border-b-2 border-gray-400 dark:border-gray-600 bg-gray-200 dark:bg-gray-800">
                        <span class="text-[11px] font-bold text-gray-900 dark:text-white">Rezultāts</span>
                        <span class="text-xs font-bold {{ $yearResult >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">{{ ($yearResult >= 0 ? '+' : '') . number_format($yearResult, 2, ',', ' ') }}</span>
                    </div>
                    <div class="px-3 pt-1.5 pb-0.5">
                        <span class="text-[10px] uppercase tracking-wide text-gray-700 dark:text-gray-300">Kontu atlikumi (gada beigas)</span>
                    </div>
                    @foreach($accounts as $acc)
                        @php $bal = $yearLastAccountBalances[$acc->id] ?? 0; @endphp
                        <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5 last:border-0">
                            <span class="text-gray-800 dark:text-gray-200 truncate mr-1" title="{{ $acc->name }}">{{ mb_substr($acc->name, 0, 16) }}</span>
                            <span class="font-medium whitespace-nowrap {{ $bal < 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($bal, 2, ',', ' ') }}</span>
                        </div>
                    @endforeach
                </div>

            </div>
        </div>
        @endif

        {{-- Monthly Summary Table --}}
        @php
            $incomeColCount  = count($journalIncomeColumns);
            $expenseColCount = count($journalExpenseColumns);
            $yearOpeningTotal = array_sum($yearOpeningAccountBalances ?? []);
        @endphp
        <div x-data="{}"
             x-init="if (!Alpine.store('yearView')) { Alpine.store('yearView', { expandedMonths: [] }); }"
             class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300 dark:border-gray-700 text-xs">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th rowspan="2" class="px-2 py-2 border border-gray-300 dark:border-gray-700 align-bottom w-12"></th>
                        <th rowspan="2" class="px-3 py-2 border border-gray-300 dark:border-gray-700 text-start text-sm font-medium text-gray-950 dark:text-white align-bottom whitespace-nowrap" style="min-width:160px">Mēnesis</th>
                        {{-- Per-account group headers (3 sub-cols each: Ieņ. | Izd. | Atlikums) --}}
                        @foreach($accounts as $acc)
                        <th colspan="3" class="px-1 py-2 border border-gray-300 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/30 text-center text-sm font-medium text-gray-950 dark:text-white" title="{{ $acc->name }}">{{ mb_substr($acc->name, 0, 14) }}</th>
                        @endforeach
                        <th rowspan="2" class="px-3 py-2 border border-gray-300 dark:border-gray-700 text-end text-sm font-medium text-gray-950 dark:text-white align-bottom">Bilance</th>
                        <th colspan="{{ $incomeColCount + 1 }}" class="px-1 py-2 border border-gray-300 dark:border-gray-700 bg-green-50 dark:bg-green-900/30 text-center text-sm font-medium text-gray-950 dark:text-white">Ieņēmumi (EUR)</th>
                        <th colspan="{{ $expenseColCount + 1 }}" class="px-1 py-2 border border-gray-300 dark:border-gray-700 bg-red-50 dark:bg-red-900/30 text-center text-sm font-medium text-gray-950 dark:text-white">Izdevumi (EUR)</th>
                    </tr>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-center text-[10px]">
                        {{-- Per-account sub-headers --}}
                        @foreach($accounts as $acc)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-green-600 dark:text-green-400 bg-gray-100 dark:bg-gray-800 whitespace-nowrap" style="min-width:85px" title="Ieņēmumi">Ieņ.</th>
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-red-600 dark:text-red-400 bg-gray-100 dark:bg-gray-800 whitespace-nowrap" style="min-width:85px" title="Izdevumi">Izd.</th>
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 whitespace-nowrap" style="min-width:90px" title="Atlikums">Atlikums</th>
                        @endforeach
                        {{-- Income analysis sub-headers --}}
                        @foreach($journalIncomeColumns as $col)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 whitespace-nowrap" style="min-width:75px" title="{{ $col['name'] }}">{{ $col['abbr'] }}</th>
                        @endforeach
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap" style="min-width:80px">Kopā</th>
                        {{-- Expense analysis sub-headers --}}
                        @foreach($journalExpenseColumns as $col)
                            <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 whitespace-nowrap" style="min-width:75px" title="{{ $col['name'] }}">{{ $col['abbr'] }}</th>
                        @endforeach
                        <th class="px-1 py-1 border border-gray-300 dark:border-gray-700 font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap" style="min-width:80px">Kopā</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Opening Balance Row --}}
                    <tr class="bg-yellow-50 dark:bg-yellow-900/10 font-bold text-gray-700 dark:text-gray-300">
                        <td class="border border-gray-300 dark:border-gray-700"></td>
                        <td class="px-3 py-2 text-xs text-right border border-gray-300 dark:border-gray-700">Sākuma atlikums:</td>
                        @foreach($accounts as $acc)
                            <td class="border border-gray-300 dark:border-gray-700"></td>
                            <td class="border border-gray-300 dark:border-gray-700"></td>
                            <td class="px-2 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 {{ ($yearOpeningAccountBalances[$acc->id] ?? 0) < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ number_format($yearOpeningAccountBalances[$acc->id] ?? 0, 2, ',', ' ') }}
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 {{ $yearOpeningTotal < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-gray-100' }}">
                            {{ number_format($yearOpeningTotal, 2, ',', ' ') }}
                        </td>
                        <td colspan="{{ $incomeColCount + 1 + $expenseColCount + 1 }}" class="border border-gray-300 dark:border-gray-700"></td>
                    </tr>

                    @foreach($monthlySummary as $mSummary)
                        {{-- Month summary row (clickable to expand categories) --}}
                        <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer border-b border-gray-200 dark:border-white/5"
                            @click="$store.yearView.expandedMonths.includes({{ $mSummary['month_number'] }})
                                ? $store.yearView.expandedMonths = $store.yearView.expandedMonths.filter(m => m !== {{ $mSummary['month_number'] }})
                                : $store.yearView.expandedMonths.push({{ $mSummary['month_number'] }})">
                            {{-- Action button — first column --}}
                            <td class="px-1 py-1 text-center border border-gray-300 dark:border-gray-700" @click.stop>
                                <x-filament::button size="xs" color="gray" icon="heroicon-o-eye"
                                    wire:click="viewMonthDetails({{ $mSummary['month_number'] }})">
                                    Skatīt
                                </x-filament::button>
                            </td>
                            {{-- Month name + status badges --}}
                            <td class="px-3 py-2 text-sm font-medium text-gray-950 dark:text-white border border-gray-300 dark:border-gray-700 whitespace-nowrap">
                                <span x-text="$store.yearView && $store.yearView.expandedMonths.includes({{ $mSummary['month_number'] }}) ? '▾' : '▸'" class="text-gray-400 text-[10px] mr-1"></span>{{ $mSummary['month'] }}
                                @if($mSummary['tx_total'] > 0)
                                    @if($mSummary['all_completed'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400" title="Visi {{ $mSummary['tx_total'] }} darījumi apstiprināti">✓</span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400" title="{{ $mSummary['tx_completed'] }}/{{ $mSummary['tx_total'] }} apstiprināti">{{ $mSummary['tx_completed'] }}/{{ $mSummary['tx_total'] }}</span>
                                    @endif
                                    @if($mSummary['columns_ok'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400" title="Žurnāla ailes aizpildītas">OK</span>
                                    @endif
                                @endif
                            </td>
                            {{-- Per-account: Ieņ. | Izd. | Atlikums --}}
                            @foreach($accounts as $acc)
                                <td class="px-2 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 text-success-700 dark:text-success-400 whitespace-nowrap">
                                    @if(($mSummary['account_income'][$acc->id] ?? 0) > 0){{ number_format($mSummary['account_income'][$acc->id], 2, ',', ' ') }}@endif
                                </td>
                                <td class="px-2 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 text-danger-700 dark:text-danger-400 whitespace-nowrap">
                                    @if(($mSummary['account_expense'][$acc->id] ?? 0) > 0){{ number_format($mSummary['account_expense'][$acc->id], 2, ',', ' ') }}@endif
                                </td>
                                <td class="px-2 py-2 text-xs text-end font-medium border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 whitespace-nowrap {{ ($mSummary['account_balances'][$acc->id] ?? 0) < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ number_format($mSummary['account_balances'][$acc->id] ?? 0, 2, ',', ' ') }}
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-xs text-end font-medium border border-gray-300 dark:border-gray-700 whitespace-nowrap {{ $mSummary['balance'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ number_format($mSummary['balance'], 2, ',', ' ') }}
                            </td>
                            @foreach($journalIncomeColumns as $i => $col)
                            <td class="px-2 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 text-success-700 dark:text-success-400 whitespace-nowrap">
                                @if(isset($mSummary['income_cols'][$i]) && $mSummary['income_cols'][$i] > 0){{ number_format($mSummary['income_cols'][$i], 2, ',', ' ') }}@endif
                            </td>
                            @endforeach
                            <td class="px-2 py-2 text-xs text-end font-bold border border-gray-300 dark:border-gray-700 bg-green-50 dark:bg-green-900/10 text-success-700 dark:text-success-400 whitespace-nowrap">
                                @if($mSummary['income_kopaa'] > 0){{ number_format($mSummary['income_kopaa'], 2, ',', ' ') }}@endif
                            </td>
                            @foreach($journalExpenseColumns as $i => $col)
                            <td class="px-2 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 text-danger-700 dark:text-danger-400 whitespace-nowrap">
                                @if(isset($mSummary['expense_cols'][$i]) && $mSummary['expense_cols'][$i] > 0){{ number_format($mSummary['expense_cols'][$i], 2, ',', ' ') }}@endif
                            </td>
                            @endforeach
                            <td class="px-2 py-2 text-xs text-end font-bold border border-gray-300 dark:border-gray-700 bg-red-50 dark:bg-red-900/10 text-danger-700 dark:text-danger-400 whitespace-nowrap">
                                @if($mSummary['expense_kopaa'] > 0){{ number_format($mSummary['expense_kopaa'], 2, ',', ' ') }}@endif
                            </td>
                        </tr>

                        {{-- Expandable: per-category breakdown rows --}}
                        @foreach($mSummary['categories'] as $cat)
                            <tr x-show="$store.yearView && $store.yearView.expandedMonths.includes({{ $mSummary['month_number'] }})"
                                class="{{ $cat['type'] === 'INCOME' ? 'bg-green-50/40 dark:bg-green-900/5' : 'bg-red-50/40 dark:bg-red-900/5' }}">
                                <td class="border border-gray-200 dark:border-gray-700"></td>
                                <td class="pl-7 pr-2 py-1 text-[10px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400">
                                    <span class="{{ $cat['type'] === 'INCOME' ? 'text-success-500' : 'text-danger-500' }} mr-1">{{ $cat['type'] === 'INCOME' ? '↑' : '↓' }}</span>
                                    {{ $cat['name'] }}
                                    @if($cat['vid_column'] > 0)<span class="text-gray-400 text-[9px] ml-1">(kol.{{ $cat['vid_column'] }})</span>@endif
                                </td>
                                {{-- 3 empty cells per account --}}
                                @foreach($accounts as $acc)
                                    <td class="border border-gray-200 dark:border-gray-700"></td>
                                    <td class="border border-gray-200 dark:border-gray-700"></td>
                                    <td class="border border-gray-200 dark:border-gray-700"></td>
                                @endforeach
                                <td class="border border-gray-200 dark:border-gray-700"></td>
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
                            </tr>
                        @endforeach
                    @endforeach

                    {{-- Total Row --}}
                    <tr class="bg-gray-100 dark:bg-white/5 font-bold border-t-2 border-gray-400">
                        <td class="border border-gray-300 dark:border-gray-700"></td>
                        <td class="px-3 py-2 text-sm text-gray-950 dark:text-white border border-gray-300 dark:border-gray-700">GADA KOPĀ</td>
                        {{-- Per-account totals: sum of Ieņ./Izd. + last month's Atlikums --}}
                        @foreach($accounts as $acc)
                        <td class="px-2 py-2 text-xs text-end text-success-700 dark:text-success-400 border border-gray-300 dark:border-gray-700 whitespace-nowrap">
                            {{ number_format(collect($monthlySummary)->sum(fn($m) => $m['account_income'][$acc->id] ?? 0), 2, ',', ' ') }}
                        </td>
                        <td class="px-2 py-2 text-xs text-end text-danger-700 dark:text-danger-400 border border-gray-300 dark:border-gray-700 whitespace-nowrap">
                            {{ number_format(collect($monthlySummary)->sum(fn($m) => $m['account_expense'][$acc->id] ?? 0), 2, ',', ' ') }}
                        </td>
                        <td class="px-2 py-2 text-xs text-end font-bold border border-gray-300 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/10 whitespace-nowrap {{ (collect($monthlySummary)->last()['account_balances'][$acc->id] ?? 0) < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">
                            {{ number_format(collect($monthlySummary)->last()['account_balances'][$acc->id] ?? 0, 2, ',', ' ') }}
                        </td>
                        @endforeach
                        <td class="px-3 py-2 text-xs text-end border border-gray-300 dark:border-gray-700 whitespace-nowrap {{ (collect($monthlySummary)->last()['balance'] ?? 0) >= 0 ? 'text-gray-900 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ number_format(collect($monthlySummary)->last()['balance'] ?? 0, 2, ',', ' ') }}
                        </td>
                        @foreach($journalIncomeColumns as $i => $col)
                        <td class="px-2 py-2 text-xs text-end text-success-700 dark:text-success-400 border border-gray-300 dark:border-gray-700 whitespace-nowrap">
                            {{ number_format(collect($monthlySummary)->sum(fn($m) => $m['income_cols'][$i] ?? 0), 2, ',', ' ') }}
                        </td>
                        @endforeach
                        <td class="px-2 py-2 text-xs text-end font-bold bg-green-50 dark:bg-green-900/10 text-success-700 dark:text-success-400 border border-gray-300 dark:border-gray-700 whitespace-nowrap">
                            {{ number_format(collect($monthlySummary)->sum('income_kopaa'), 2, ',', ' ') }}
                        </td>
                        @foreach($journalExpenseColumns as $i => $col)
                        <td class="px-2 py-2 text-xs text-end text-danger-700 dark:text-danger-400 border border-gray-300 dark:border-gray-700 whitespace-nowrap">
                            {{ number_format(collect($monthlySummary)->sum(fn($m) => $m['expense_cols'][$i] ?? 0), 2, ',', ' ') }}
                        </td>
                        @endforeach
                        <td class="px-2 py-2 text-xs text-end font-bold bg-red-50 dark:bg-red-900/10 text-danger-700 dark:text-danger-400 border border-gray-300 dark:border-gray-700 whitespace-nowrap">
                            {{ number_format(collect($monthlySummary)->sum('expense_kopaa'), 2, ',', ' ') }}
                        </td>
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
                    {{ mb_strtoupper($latvianMonths[$selectedMonth] ?? '', 'UTF-8') }} {{ $selectedYear }}
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
            <div class="mb-4 bg-gray-100 dark:bg-gray-800 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="flex divide-x divide-gray-300 dark:divide-white/10">

                    {{-- INCOME --}}
                    <div class="flex-1 min-w-0">
                        <div class="px-3 py-1 bg-green-100 dark:bg-green-900/20 flex justify-between items-center border-b border-green-200 dark:border-green-900/40">
                            <span class="text-[11px] font-semibold text-green-900 dark:text-green-300 uppercase tracking-wide">Ieņēmumi</span>
                            <span class="text-xs font-bold text-green-800 dark:text-green-400">{{ number_format($monthData['income'], 2, ',', ' ') }}</span>
                        </div>
                        <table class="w-full">
                            @foreach($journalIncomeColumns as $i => $col)
                                @php $ct = $monthData['income_cols'][$i] ?? 0; @endphp
                                <tr class="{{ $ct > 0 ? 'bg-white/50 dark:bg-white/5' : 'opacity-40 bg-white/30 dark:bg-white/0' }} border-b border-gray-200 dark:border-white/5">
                                    <td class="px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-300 truncate max-w-[1px]" title="{{ $col['name'] }}">
                                        <span class="font-mono text-gray-500 dark:text-gray-500">{{ $col['abbr'] }}</span>
                                        <span class="ml-1">{{ $col['name'] }}</span>
                                    </td>
                                    <td class="px-2 py-0.5 text-[11px] text-right font-medium text-green-700 dark:text-green-400 whitespace-nowrap w-24">{{ $ct > 0 ? number_format($ct, 2, ',', ' ') : '—' }}</td>
                                </tr>
                            @endforeach
                            @if($incomeUncategorized > 0.005)
                                <tr class="border-b border-orange-200 dark:border-orange-900/30 bg-orange-100/60 dark:bg-orange-900/10">
                                    <td class="px-2 py-0.5 text-[11px] text-orange-700 dark:text-orange-400 italic">Nav kartēti</td>
                                    <td class="px-2 py-0.5 text-[11px] text-right font-medium text-orange-700 dark:text-orange-400 w-24">{{ number_format($incomeUncategorized, 2, ',', ' ') }}</td>
                                </tr>
                            @endif
                            <tr class="bg-green-100 dark:bg-green-900/20">
                                <td class="px-2 py-1 text-[11px] font-semibold text-green-900 dark:text-green-300">Kopā kartēti</td>
                                <td class="px-2 py-1 text-[11px] text-right font-bold text-green-800 dark:text-green-400 w-24">{{ number_format($monthData['income_kopaa'], 2, ',', ' ') }}</td>
                            </tr>
                        </table>
                    </div>

                    {{-- EXPENSE --}}
                    <div class="flex-1 min-w-0">
                        <div class="px-3 py-1 bg-red-100 dark:bg-red-900/20 flex justify-between items-center border-b border-red-200 dark:border-red-900/40">
                            <span class="text-[11px] font-semibold text-red-900 dark:text-red-300 uppercase tracking-wide">Izdevumi</span>
                            <span class="text-xs font-bold text-red-800 dark:text-red-400">{{ number_format($monthData['expense'], 2, ',', ' ') }}</span>
                        </div>
                        <table class="w-full">
                            @foreach($journalExpenseColumns as $i => $col)
                                @php $ct = $monthData['expense_cols'][$i] ?? 0; @endphp
                                <tr class="{{ $ct > 0 ? 'bg-white/50 dark:bg-white/5' : 'opacity-40 bg-white/30 dark:bg-white/0' }} border-b border-gray-200 dark:border-white/5">
                                    <td class="px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-300 truncate max-w-[1px]" title="{{ $col['name'] }}">
                                        <span class="font-mono text-gray-500 dark:text-gray-500">{{ $col['abbr'] }}</span>
                                        <span class="ml-1">{{ $col['name'] }}</span>
                                    </td>
                                    <td class="px-2 py-0.5 text-[11px] text-right font-medium text-red-700 dark:text-red-400 whitespace-nowrap w-24">{{ $ct > 0 ? number_format($ct, 2, ',', ' ') : '—' }}</td>
                                </tr>
                            @endforeach
                            @if($expenseUncategorized > 0.005)
                                <tr class="border-b border-orange-200 dark:border-orange-900/30 bg-orange-100/60 dark:bg-orange-900/10">
                                    <td class="px-2 py-0.5 text-[11px] text-orange-700 dark:text-orange-400 italic">Nav kartēti</td>
                                    <td class="px-2 py-0.5 text-[11px] text-right font-medium text-orange-700 dark:text-orange-400 w-24">{{ number_format($expenseUncategorized, 2, ',', ' ') }}</td>
                                </tr>
                            @endif
                            <tr class="bg-red-100 dark:bg-red-900/20">
                                <td class="px-2 py-1 text-[11px] font-semibold text-red-900 dark:text-red-300">Kopā kartēti</td>
                                <td class="px-2 py-1 text-[11px] text-right font-bold text-red-800 dark:text-red-400 w-24">{{ number_format($monthData['expense_kopaa'], 2, ',', ' ') }}</td>
                            </tr>
                        </table>
                    </div>

                    {{-- BALANCE + ACCOUNTS --}}
                    <div class="w-44 shrink-0">
                        <div class="px-3 py-1 bg-gray-200 dark:bg-gray-800 border-b border-gray-300 dark:border-white/10">
                            <span class="text-[11px] font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Bilance</span>
                        </div>
                        <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5">
                            <span class="text-gray-800 dark:text-gray-200">Ieņēmumi</span>
                            <span class="text-green-700 dark:text-green-400 font-medium">+{{ number_format($monthData['income'], 2, ',', ' ') }}</span>
                        </div>
                        <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5">
                            <span class="text-gray-800 dark:text-gray-200">Izdevumi</span>
                            <span class="text-red-700 dark:text-red-400 font-medium">−{{ number_format($monthData['expense'], 2, ',', ' ') }}</span>
                        </div>
                        <div class="px-3 py-1 flex justify-between border-b-2 border-gray-400 dark:border-gray-600 bg-gray-200 dark:bg-gray-800">
                            <span class="text-[11px] font-bold text-gray-900 dark:text-white">Rezultāts</span>
                            <span class="text-xs font-bold {{ $result >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">{{ ($result >= 0 ? '+' : '') . number_format($result, 2, ',', ' ') }}</span>
                        </div>
                        <div class="px-3 pt-1.5 pb-0.5">
                            <span class="text-[10px] uppercase tracking-wide text-gray-700 dark:text-gray-300">Kontu atlikumi</span>
                        </div>
                        @foreach($accounts as $acc)
                            @php $bal = $monthData['account_balances'][$acc->id] ?? 0; @endphp
                            <div class="px-3 py-0.5 flex justify-between text-[11px] border-b border-gray-200 dark:border-white/5 last:border-0">
                                <span class="text-gray-800 dark:text-gray-200 truncate mr-1" title="{{ $acc->name }}">{{ mb_substr($acc->name, 0, 14) }}</span>
                                <span class="font-medium whitespace-nowrap {{ $bal < 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($bal, 2, ',', ' ') }}</span>
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
            $fcCount           = count($foreignCurrencies);
            $detailColSpan     = 8 + $fcCount + count($accounts) * 3 + $incomeColCount + 1 + $expenseColCount + 1 + 1;
        @endphp
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
        <style>
            /* Drag/sort controls — uses plain CSS so Tailwind purge doesn't affect them */
            .jnl-drag-handle {
                cursor: grab;
                opacity: 0.25;
                color: #9ca3af;
                font-size: 11px;
                user-select: none;
                line-height: 1;
                padding: 0 1px;
                transition: opacity 0.15s;
            }
            .transaction-row:hover .jnl-drag-handle { opacity: 1; color: #6b7280; }
            .jnl-drag-handle:active { cursor: grabbing; }
            .jnl-sort-btn {
                opacity: 0;
                cursor: pointer;
                font-size: 9px;
                color: #9ca3af;
                user-select: none;
                line-height: 1;
                display: block;
                width: 100%;
                text-align: center;
                border-radius: 2px;
                transition: opacity 0.15s;
            }
            .transaction-row:hover .jnl-sort-btn { opacity: 1; }
            .transaction-row:hover .jnl-sort-btn:hover { color: #374151; background: #dbeafe; }
            /* SortableJS ghost/drag styles */
            .sortable-ghost { opacity: 0.4; background: #eff6ff !important; }
            .sortable-drag  { opacity: 0.9; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        </style>
        <div class="overflow-x-auto bg-white dark:bg-gray-900 p-4 rounded-lg shadow-sm"
             x-data="{
                 _sortable: null,
                 initSortable() {
                     if (!window.Sortable) { setTimeout(() => this.initSortable(), 150); return; }
                     if (this._sortable) { this._sortable.destroy(); this._sortable = null; }
                     const tbody = this.$refs.sortableTbody;
                     if (!tbody) return;
                     const wire = this.$wire;
                     this._sortable = Sortable.create(tbody, {
                         animation: 150,
                         handle: '.drag-handle',
                         filter: '.no-sort-row',
                         onMove: (evt) => !evt.related.classList.contains('no-sort-row'),
                         onEnd: () => {
                             const ids = [...tbody.querySelectorAll('tr.transaction-row')]
                                 .map(r => parseInt(r.dataset.txid));
                             wire.reorderTransactions(ids);
                         }
                     });
                 }
             }"
             x-init="
                 if (!Alpine.store('journal')) { Alpine.store('journal', { expandedRows: [] }); }
                 $nextTick(() => this.initSortable());
             "
             @journal-rows-updated.window="$nextTick(() => this.initSortable())">
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

                        {{-- Ārzemju valūtu kolonnas (dinamiski, tikai ja mēnesī ir tādi darījumi) --}}
                        @foreach($foreignCurrencies as $curr)
                            <th rowspan="2" class="px-1 py-1 border border-gray-300 dark:border-gray-700 align-bottom bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-300 text-center" style="min-width: 55px;" title="Oriģinālā summa {{ $curr }}">{{ $curr }}</th>
                        @endforeach

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
                        @foreach($foreignCurrencies as $curr)
                            <th class="border border-gray-300 dark:border-gray-700 bg-yellow-50 dark:bg-yellow-900/20">{{ $colNum++ }}</th>
                        @endforeach
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
                <tbody class="bg-white dark:bg-gray-900" x-ref="sortableTbody">
                    {{-- Opening Balances Row --}}
                    <tr class="no-sort-row bg-yellow-50 dark:bg-yellow-900/10 font-bold text-gray-700 dark:text-gray-300">
                        <td colspan="7" class="px-2 py-2 border border-gray-300 dark:border-gray-700 text-right text-xs">Sākuma atlikums:</td>
                        <td class="border border-gray-300 dark:border-gray-700"></td>
                        @foreach($foreignCurrencies as $curr)
                            <td class="border border-gray-300 dark:border-gray-700 bg-yellow-50/40 dark:bg-yellow-900/10"></td>
                        @endforeach
                        @foreach($accounts as $acc)
                            <td colspan="2" class="border border-gray-300 dark:border-gray-700"></td>
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right hover:bg-yellow-100 dark:hover:bg-yellow-800/30 cursor-pointer group/bal"
                                title="Klikšķiniet, lai labotu {{ $acc->name }} sākuma atlikumu">
                                <div wire:click="mountOpeningBalanceModal({{ $acc->id }})" class="flex items-center justify-end gap-1">
                                    <span>{{ number_format($opening_balances[$acc->id] ?? 0, 2, ',', ' ') }}</span>
                                    <span class="text-[8px] text-yellow-500 opacity-0 group-hover/bal:opacity-100">✎</span>
                                </div>
                                @if(($acc->currency ?? 'EUR') !== 'EUR' && ($acc->balance_exchange_rate ?? 0) > 0)
                                    @php $origAmt = round(($acc->balance ?? 0) * $acc->balance_exchange_rate, 2); @endphp
                                    <div class="text-[9px] text-yellow-600 dark:text-yellow-400 text-right">
                                        {{ number_format($origAmt, 2, ',', ' ') }} {{ $acc->currency }}
                                    </div>
                                @endif
                            </td>
                        @endforeach
                        <td colspan="{{ $totalAnalysisCols }}" class="border border-gray-300 dark:border-gray-700"></td>
                    </tr>

                    @foreach($rows as $row)
                    @if(!$showOnlyInvalid || !$row['is_mapped'])
                        <tr wire:key="row-{{ $row['entry_number'] }}"
                            class="transaction-row group cursor-pointer transition-colors duration-100 {{ in_array($row['transaction_type'], ['EXPENSE', 'FEE']) ? 'bg-red-50/50 dark:bg-red-900/10 hover:bg-red-100/60 dark:hover:bg-red-900/25' : 'hover:bg-sky-50/70 dark:hover:bg-sky-900/20' }}"
                            data-txid="{{ $row['transaction_id'] }}"
                            @click="$store.journal.expandedRows.includes({{ $row['entry_number'] }}) ? $store.journal.expandedRows = $store.journal.expandedRows.filter(id => id !== {{ $row['entry_number'] }}) : $store.journal.expandedRows.push({{ $row['entry_number'] }})">

                            {{-- 1. Identifikācija --}}
                            <td class="px-0.5 py-0 border border-gray-300 dark:border-gray-700 text-center sticky left-0 z-10 font-mono font-bold text-xs bg-white dark:bg-gray-900 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20 text-gray-900 dark:text-gray-100" title="Nr. — vilkt vai ▲▼: kārtot">
                                <div style="display:flex; align-items:center; justify-content:center; gap:2px; line-height:1;">
                                    {{-- Drag handle --}}
                                    <span class="drag-handle jnl-drag-handle" @click.stop title="Vilkt, lai pārkārtotu">⠿</span>
                                    {{-- Up/Down arrows + number --}}
                                    <div style="display:flex; flex-direction:column; align-items:center; line-height:1;">
                                        <span class="jnl-sort-btn" @click.stop wire:click="moveTransactionUp({{ $row['transaction_id'] }})">▲</span>
                                        <span style="padding: 2px 0;">{{ $row['entry_number'] }}</span>
                                        <span class="jnl-sort-btn" @click.stop wire:click="moveTransactionDown({{ $row['transaction_id'] }})">▼</span>
                                    </div>
                                </div>
                            </td>
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

                            {{-- Ārzemju valūtu oriģinālās summas --}}
                            @foreach($foreignCurrencies as $curr)
                                <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-[10px] bg-yellow-50/30 dark:bg-yellow-900/10 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20">
                                    @if(($row['transaction_currency'] ?? 'EUR') === $curr)
                                        <span class="text-yellow-800 dark:text-yellow-300 font-medium">{{ number_format(abs($row['transaction_amount_original']), 2, ',', ' ') }}</span>
                                    @endif
                                </td>
                            @endforeach

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
                                @if($row['status'] === 'COMPLETED' && $row['transaction_type'] == 'INCOME' && in_array($row['category_vid_column'], $col['vid_columns']))
                                    {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                @endif
                            </td>
                            @endforeach
                            {{-- Ieņēmumi Kopā --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right font-bold text-green-600 dark:text-green-400">
                                @if($row['status'] === 'COMPLETED' && $row['transaction_type'] == 'INCOME' && $row['is_mapped'])
                                    {{ number_format($row['transaction_amount'], 2, ',', ' ') }}
                                @endif
                            </td>

                            {{-- 4. Izdevumu analīze (dynamic columns) --}}
                            @foreach($journalExpenseColumns as $col)
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right text-gray-900 dark:text-gray-100" title="{{ $col['name'] }}">
                                @if($row['status'] === 'COMPLETED' && $row['transaction_type'] == 'EXPENSE' && in_array($row['category_vid_column'], $col['vid_columns']))
                                    {{ number_format(abs($row['transaction_amount']), 2, ',', ' ') }}
                                @endif
                            </td>
                            @endforeach
                            {{-- Izdevumi Kopā --}}
                            <td class="px-1 py-1 border border-gray-300 dark:border-gray-700 text-right font-bold text-red-600 dark:text-red-400">
                                @if($row['status'] === 'COMPLETED' && $row['transaction_type'] == 'EXPENSE' && $row['is_mapped'])
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
                        <tr x-show="$store.journal && $store.journal.expandedRows.includes({{ $row['entry_number'] }})" class="no-sort-row bg-blue-50/50 dark:bg-blue-900/10">
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
                    <tr class="no-sort-row bg-yellow-100 dark:bg-yellow-900/20 font-bold text-gray-800 dark:text-gray-200 border-t-2 border-gray-400">
                        <td colspan="7" class="px-2 py-2 border border-gray-300 dark:border-gray-700 text-right">Beigu atlikums:</td>
                        <td class="border border-gray-300 dark:border-gray-700"></td>
                        @foreach($foreignCurrencies as $curr)
                            <td class="border border-gray-300 dark:border-gray-700 bg-yellow-50/40 dark:bg-yellow-900/10"></td>
                        @endforeach
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
