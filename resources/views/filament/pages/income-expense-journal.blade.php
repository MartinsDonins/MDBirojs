<x-filament-panels::page>
    @if($selectedMonth === null)
        {{-- Year Summary View --}}
        <div class="mb-6">
            <div class="text-center mb-4">
                <h2 class="text-2xl font-bold">
                    SAIMNIECISKĀS DARBĪBAS IEŅĒMUMU UN IZDEVUMU UZSKAITES ŽURNĀLS
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    Par {{ $selectedYear }}. gadu
                </p>
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
                        Bilance {{ $selectedYear }}. gadā
                    </div>
                    <div class="text-2xl font-bold {{ $summary['balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }} mt-2">
                        {{ number_format($summary['balance'], 2, ',', ' ') }} EUR
                    </div>
                </x-filament::card>
            </div>
        </div>

        {{-- Monthly Summary Table --}}
        {{ $this->table }}

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
                            Bilance
                        </div>
                        <div class="text-2xl font-bold {{ ($monthData['income'] - $monthData['expense']) >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }} mt-2">
                            {{ number_format($monthData['income'] - $monthData['expense'], 2, ',', ' ') }} EUR
                        </div>
                    </x-filament::card>
                </div>
            @endif
        </div>

        {{-- Detailed Transactions Table --}}
        {{ $this->table }}
    @endif
</x-filament-panels::page>
