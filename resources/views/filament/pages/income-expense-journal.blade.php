<x-filament-panels::page>
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

    {{-- Transactions Table --}}
    {{ $this->table }}
</x-filament-panels::page>
