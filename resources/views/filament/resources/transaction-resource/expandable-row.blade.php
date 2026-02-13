<div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
    <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="sm:col-span-1">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Apraksts</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->description ?? '-' }}</dd>
        </div>
        
        <div class="sm:col-span-1">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Kontrahents</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->counterparty_name ?? '-' }}</dd>
        </div>
        
        <div class="sm:col-span-1">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Kontrahenta konts</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $record->counterparty_account ?? '-' }}</dd>
        </div>
        
        <div class="sm:col-span-1">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Atsauce</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->reference ?? '-' }}</dd>
        </div>
        
        <div class="sm:col-span-1">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Summa</dt>
            <dd class="mt-1 text-sm font-semibold {{ $record->amount > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ number_format(abs($record->amount), 2, ',', ' ') }} {{ $record->currency }}
            </dd>
        </div>
        
        <div class="sm:col-span-1">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Datums</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->occurred_at->format('d.m.Y') }}</dd>
        </div>
        
        @if($record->raw_payload && isset($record->raw_payload['Bankas_kods']))
        <div class="sm:col-span-1">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Bankas kods</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-700">
                    {{ $record->raw_payload['Bankas_kods'] }}
                </span>
            </dd>
        </div>
        @endif
    </dl>
</div>
