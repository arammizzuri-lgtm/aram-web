<x-filament-panels::page>
    <form wire:submit="save" class="aram-form">
        {{ $this->form }}

        <div class="aram-form-actions">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">
                Save settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
