<x-filament-panels::page>

    {{-- Summary stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Kopā ieguldīts</p>
            <p class="mt-1 text-2xl font-bold text-success-600 dark:text-success-400">
                € {{ number_format($totalInvested, 2, ',', ' ') }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ieguldītāji</p>
            <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">
                {{ $investorCount }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Darījumi</p>
            <p class="mt-1 text-2xl font-bold text-gray-700 dark:text-gray-200">
                {{ $transactionCount }}
            </p>
        </div>

    </div>

    {{-- Investments table --}}
    {{ $this->table }}

</x-filament-panels::page>
