<x-filament-panels::page>

    {{-- Tab navigation --}}
    <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700 mb-6">
        @foreach(['profile' => ['Profils', 'heroicon-o-user'], 'users' => ['Lietotāji', 'heroicon-o-users'], 'coredigify' => ['CoreDigify savienojums', 'heroicon-o-cloud-arrow-up']] as $tab => [$label, $icon])
            <button
                wire:click="$set('activeTab', '{{ $tab }}')"
                class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-b-2 transition-colors
                    {{ $activeTab === $tab
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                <x-filament::icon :icon="$icon" class="w-4 h-4" />
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ── Profils tab ──────────────────────────────────────────────────────── --}}
    @if($activeTab === 'profile')
        <x-filament::section heading="Mans profils" icon="heroicon-o-user">
            <form wire:submit="saveProfile" class="space-y-4">
                {{ $this->profileForm }}
                <div class="flex justify-end pt-2">
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Saglabāt profilu
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif

    {{-- ── Lietotāji tab ────────────────────────────────────────────────────── --}}
    @if($activeTab === 'users')
        <x-filament::section heading="Sistēmas lietotāji" icon="heroicon-o-users">
            <div class="flex justify-end mb-4">
                {{ ($this->createUserAction)([]) }}
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Vārds</th>
                            <th class="px-4 py-3 text-left">E-pasts</th>
                            <th class="px-4 py-3 text-left">Pievienots</th>
                            <th class="px-4 py-3 text-center">Darbības</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($users as $user)
                            <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-3 font-medium">
                                    {{ $user['name'] }}
                                    @if($user['id'] === auth()->id())
                                        <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-400">Es</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $user['email'] }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ \Carbon\Carbon::parse($user['created_at'])->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2" @click.stop>
                                        {{ ($this->changePasswordAction)(['userId' => $user['id']]) }}
                                        @if($user['id'] !== auth()->id())
                                            {{ ($this->deleteUserAction)(['userId' => $user['id']]) }}
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-400">Nav lietotāju</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- ── CoreDigify savienojums tab ───────────────────────────────────────── --}}
    @if($activeTab === 'coredigify')
        <x-filament::section heading="CoreDigify savienojums" icon="heroicon-o-cloud-arrow-up">
            <div class="mb-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm text-blue-700 dark:text-blue-300">
                <strong>Izejošā integrācija:</strong> MDBirojs nosūta maksājumus uz CoreDigify automātiski un manuāli.<br>
                <strong>Ienākošā API:</strong> CoreDigify var pieprasīt darījumus no MDBirojs, izmantojot zemāk redzamo atslēgu.
            </div>

            {{-- MDBirojs API endpoint info block --}}
            <div class="mb-6 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-o-link" class="w-3.5 h-3.5" />
                    MDBirojs API endpointi (ienākošie pieprasījumi no CoreDigify)
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @foreach([
                        ['label' => 'Meklēt darījumus', 'method' => 'POST', 'path' => '/coredigify/transactions/search'],
                        ['label' => 'Darījums pēc ID',  'method' => 'GET',  'path' => '/coredigify/transactions/{id}'],
                    ] as $ep)
                        <div class="flex items-center gap-3 px-4 py-2.5" x-data>
                            <span class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded font-mono
                                {{ $ep['method'] === 'POST' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' }}">
                                {{ $ep['method'] }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">{{ $ep['label'] }}</span>
                            <code class="flex-1 text-xs font-mono text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 rounded px-2 py-1 truncate"
                                id="url-{{ $loop->index }}">{{ $apiBaseUrl }}{{ $ep['path'] }}</code>
                            <button type="button"
                                x-on:click="navigator.clipboard.writeText('{{ $apiBaseUrl }}{{ $ep['path'] }}'); $el.textContent = '✓'; setTimeout(() => $el.textContent = 'Kopēt', 1500)"
                                class="shrink-0 text-[11px] px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 transition-colors">
                                Kopēt
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            <form wire:submit="saveCoredigify" class="space-y-4">
                {{ $this->coredigifyForm }}
                <div class="flex justify-end pt-2">
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Saglabāt
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
