<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button
                type="submit"
                icon="heroicon-o-check"
            >
                Saglabāt
            </x-filament::button>

            <x-filament::button
                type="button"
                wire:click="saveAndRun"
                color="success"
                icon="heroicon-o-play"
            >
                Saglabāt un izpildīt
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
