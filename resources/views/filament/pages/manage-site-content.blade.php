<x-filament-panels::page>
    {{-- One section at a time (see ManageSiteContent): each link opens its
         own section, in English, Kurdish and Arabic. --}}
    <x-filament::tabs label="Sections" class="flex-wrap">
        @foreach (\App\Filament\Pages\ManageSiteContent::sections() as $slug => $name)
            <x-filament::tabs.item tag="a" :href="static::getUrl(['section' => $slug])" :active="$this->section === $slug">
                {{ $name }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-end gap-4">
            <span class="text-sm text-gray-500 dark:text-gray-400">Saves this section only</span>
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">
                Save changes
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
