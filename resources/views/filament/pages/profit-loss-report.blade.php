<x-filament-panels::page>

    {{-- ════════════════════════════════════════════════════════════
         Chart.js (loaded once via CDN)
    ════════════════════════════════════════════════════════════ --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    {{-- Pass PHP data to JS (ascending order for charts) --}}
    <script>
        window._plYearlyData  = @json(array_values(array_reverse($yearlyData)));
        window._plMonthlyData = @json($monthlyData);
    </script>

    {{-- ════════════════════════════════════════════════════════════
         CHARTS  (wire:ignore = Livewire will not touch this subtree)
    ════════════════════════════════════════════════════════════ --}}
    <div
        wire:ignore
        class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6"
        x-data="{
            init() {
                this.$nextTick(() => {
                    this.buildYearlyChart();
                    this.buildMonthlyChart();
                });
            },

            buildYearlyChart() {
                const ctx = document.getElementById('plYearlyChart');
                if (!ctx || !window.Chart) return;
                const data = window._plYearlyData || [];
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(d => d.year),
                        datasets: [
                            {
                                label: 'Ieņēmumi',
                                data: data.map(d => d.income),
                                backgroundColor: 'rgba(34,197,94,0.65)',
                                borderColor: 'rgba(34,197,94,1)',
                                borderWidth: 1,
                                order: 2,
                            },
                            {
                                label: 'Izdevumi',
                                data: data.map(d => d.expense),
                                backgroundColor: 'rgba(239,68,68,0.65)',
                                borderColor: 'rgba(239,68,68,1)',
                                borderWidth: 1,
                                order: 2,
                            },
                            {
                                label: 'Peļņa / Zaudējumi',
                                type: 'line',
                                data: data.map(d => d.profit),
                                borderColor: 'rgba(59,130,246,1)',
                                backgroundColor: 'rgba(59,130,246,0.08)',
                                pointBackgroundColor: data.map(d =>
                                    d.profit >= 0 ? 'rgba(59,130,246,1)' : 'rgba(239,68,68,1)'
                                ),
                                borderWidth: 2,
                                pointRadius: 5,
                                tension: 0.3,
                                fill: false,
                                order: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12 } },
                            tooltip: {
                                callbacks: {
                                    label: c => c.dataset.label + ': ' +
                                        c.parsed.y.toLocaleString('lv-LV', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'
                                }
                            },
                        },
                        scales: {
                            y: {
                                ticks: {
                                    callback: v => v.toLocaleString('lv-LV', { minimumFractionDigits: 0 }) + ' €'
                                }
                            }
                        },
                    },
                });
            },

            buildMonthlyChart() {
                const ctx = document.getElementById('plMonthlyChart');
                if (!ctx || !window.Chart) return;
                const monthly = window._plMonthlyData || {};
                const monthLabels = ['Jan','Feb','Mar','Apr','Mai','Jūn','Jūl','Aug','Sep','Okt','Nov','Dec'];
                const palette = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
                const years = Object.keys(monthly).sort();
                const datasets = years.map((year, i) => ({
                    label: year,
                    data: Array.from({ length: 12 }, (_, m) => (monthly[year]?.[m + 1]?.profit ?? 0)),
                    borderColor: palette[i % palette.length],
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    pointRadius: 3,
                    tension: 0.3,
                }));
                new Chart(ctx, {
                    type: 'line',
                    data: { labels: monthLabels, datasets },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12 } },
                            tooltip: {
                                callbacks: {
                                    label: c => c.dataset.label + ': ' +
                                        c.parsed.y.toLocaleString('lv-LV', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'
                                }
                            },
                        },
                        scales: {
                            y: {
                                ticks: {
                                    callback: v => v.toLocaleString('lv-LV', { minimumFractionDigits: 0 }) + ' €'
                                }
                            }
                        },
                    },
                });
            },
        }"
        x-init="init()"
    >
        <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-3">Gadu tendence</h3>
            <canvas id="plYearlyChart"></canvas>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-3">Mēnešu salīdzinājums pa gadiem</h3>
            <canvas id="plMonthlyChart"></canvas>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         STARTING BALANCE INPUT
    ════════════════════════════════════════════════════════════ --}}
    <div class="mb-3 flex items-center gap-3 justify-end">
        <label class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
            Sākuma atlikums:
        </label>
        <div class="relative">
            <input
                type="text"
                wire:model.blur="startingBalance"
                class="w-36 rounded-lg border border-gray-300 dark:border-gray-600
                       bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100
                       text-sm text-right font-mono px-3 py-1.5 pr-7
                       focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-violet-400
                       transition"
                placeholder="0"
            />
            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none">€</span>
        </div>
        <span class="text-xs text-gray-400 dark:text-gray-500">
            (atlikums pirms pirmā gada — iekļauts Kopā atlikumā)
        </span>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         DATA TABLE
    ════════════════════════════════════════════════════════════ --}}
    <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <th class="px-3 py-3 w-8"></th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Gads</th>
                    <th class="px-4 py-3 text-right font-semibold text-green-700 dark:text-green-400">
                        Ieņēmumi
                        <div class="text-xs font-normal text-gray-400 dark:text-gray-500">({{ $incomeAbbr }})</div>
                    </th>
                    <th class="px-4 py-3 text-right font-semibold text-red-700 dark:text-red-400">
                        Izdevumi
                        <div class="text-xs font-normal text-gray-400 dark:text-gray-500">({{ $expenseAbbr }})</div>
                    </th>
                    <th class="px-4 py-3 text-right font-semibold text-blue-700 dark:text-blue-400">
                        Peļņa / Zaudējumi
                    </th>
                    <th class="px-4 py-3 text-right font-semibold text-violet-700 dark:text-violet-400">
                        Kopā atlikums
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse ($yearlyData as $yr)
                    @php $expanded = in_array($yr['year'], $expandedYears); @endphp

                    {{-- Year row --}}
                    <tr
                        wire:click="toggleYear({{ $yr['year'] }})"
                        class="border-b border-gray-100 dark:border-gray-800 cursor-pointer select-none
                               hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors duration-100"
                    >
                        <td class="px-3 py-3 text-center text-gray-400">
                            <x-heroicon-s-chevron-down
                                class="w-3.5 h-3.5 inline transition-transform duration-150 {{ $expanded ? '' : '-rotate-90' }}"
                            />
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900 dark:text-gray-100">
                            {{ $yr['year'] }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-green-700 dark:text-green-400">
                            {{ number_format($yr['income'], 2, ',', ' ') }} €
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-red-700 dark:text-red-400">
                            {{ number_format($yr['expense'], 2, ',', ' ') }} €
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold
                                   {{ $yr['profit'] >= 0 ? 'text-blue-700 dark:text-blue-400' : 'text-red-700 dark:text-red-400' }}">
                            {{ ($yr['profit'] >= 0 ? '+' : '') . number_format($yr['profit'], 2, ',', ' ') }} €
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold
                                   {{ ($yr['cumulative'] ?? 0) >= 0 ? 'text-violet-700 dark:text-violet-400' : 'text-red-700 dark:text-red-400' }}">
                            {{ number_format($yr['cumulative'] ?? 0, 2, ',', ' ') }} €
                        </td>
                    </tr>

                    {{-- Monthly detail rows (toggle) --}}
                    @if ($expanded && isset($monthlyData[$yr['year']]))
                        @php $prevMonthCumulative = $yr['year_opening'] ?? 0; @endphp
                        @foreach ($monthlyData[$yr['year']] as $mNum => $m)
                            @if ($m['income'] > 0 || $m['expense'] > 0)
                                <tr class="border-b border-gray-50 dark:border-gray-800/30 bg-gray-50/40 dark:bg-gray-800/20">
                                    <td class="px-3 py-1.5"></td>
                                    <td class="px-4 py-1.5 text-gray-500 dark:text-gray-400">
                                        <span class="pl-4 text-xs">{{ $m['name'] }}</span>
                                    </td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs
                                               {{ $m['income'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400' }}">
                                        {{ $m['income'] > 0 ? number_format($m['income'], 2, ',', ' ') . ' €' : '—' }}
                                    </td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs
                                               {{ $m['expense'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                                        {{ $m['expense'] > 0 ? number_format($m['expense'], 2, ',', ' ') . ' €' : '—' }}
                                    </td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs font-medium
                                               {{ $m['profit'] > 0 ? 'text-blue-600 dark:text-blue-400' : ($m['profit'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400') }}">
                                        {{ ($m['profit'] >= 0 ? '+' : '') . number_format($m['profit'], 2, ',', ' ') }} €
                                    </td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs
                                               {{ ($m['cumulative'] ?? 0) >= 0 ? 'text-violet-600 dark:text-violet-400/80' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($m['cumulative'] ?? 0, 2, ',', ' ') }} €
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    @endif

                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500 text-sm">
                            Nav datu. Pārliecinies, ka ir darījumi ar statusu <em>COMPLETED</em> un konfigurētas žurnāla kolonnas.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if (!empty($yearlyData))
                @php
                    $totalIncome  = array_sum(array_column($yearlyData, 'income'));
                    $totalExpense = array_sum(array_column($yearlyData, 'expense'));
                    $totalProfit  = $totalIncome - $totalExpense;
                    $finalBalance = $yearlyData[0]['cumulative'] ?? 0;  // yearlyData[0] = newest year
                @endphp
                <tfoot>
                    <tr class="bg-gray-100 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                        <td class="px-3 py-3"></td>
                        <td class="px-4 py-3 font-bold text-gray-700 dark:text-gray-300 text-sm">Kopā</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-green-700 dark:text-green-400">
                            {{ number_format($totalIncome, 2, ',', ' ') }} €
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-red-700 dark:text-red-400">
                            {{ number_format($totalExpense, 2, ',', ' ') }} €
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold
                                   {{ $totalProfit >= 0 ? 'text-blue-700 dark:text-blue-400' : 'text-red-700 dark:text-red-400' }}">
                            {{ ($totalProfit >= 0 ? '+' : '') . number_format($totalProfit, 2, ',', ' ') }} €
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-bold
                                   {{ $finalBalance >= 0 ? 'text-violet-700 dark:text-violet-400' : 'text-red-700 dark:text-red-400' }}">
                            {{ number_format($finalBalance, 2, ',', ' ') }} €
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

</x-filament-panels::page>
