<x-filament-panels::page>
    @php
        $results = $this->results();
        $missingCount = count(\App\Support\SiteText::untranslated());
        $groups = \App\Filament\Pages\ManageSiteContent::groups();
        $currentGroup = $this->currentGroup();
    @endphp

    {{-- Search every text in every language, or list what still waits for its translation --}}
    <div class="aram-sc-bar">
        <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" class="aram-sc-search">
            <x-filament::input type="search" wire:model.live.debounce.400ms="find"
                placeholder="Search every text — English, Kurdish or Arabic" aria-label="Search the site texts" />
        </x-filament::input.wrapper>
        <x-filament::button wire:click="$toggle('missing')" :color="$this->missing ? 'primary' : 'gray'"
            :outlined="! $this->missing" icon="heroicon-o-language" :badge="$missingCount ?: null" badge-color="warning">
            Missing translations
        </x-filament::button>
    </div>

    @if ($results !== null)
        <div class="aram-card aram-sc-results">
            <div class="aram-card__head">
                <h3 class="aram-card__title">
                    @if ($this->missing)
                        Texts missing their Kurdish or Arabic
                    @else
                        {{ count($results) === 60 ? 'The first 60 texts' : count($results).' '.Str::plural('text', count($results)) }} with “{{ trim($this->find) }}”
                    @endif
                </h3>
                <button type="button" class="aram-card__link" wire:click="$set('find', ''); $set('missing', false)">Back to the sections</button>
            </div>

            @forelse ($results as $r)
                <a href="{{ $r['url'] }}" class="aram-sc-hit">
                    <span class="aram-sc-hit__where">{{ $r['section'] }}</span>
                    <span class="aram-sc-hit__label">{{ $r['label'] }}</span>
                    @foreach ($r['values'] as $lang => $value)
                        <span class="aram-sc-hit__val" @if ($lang !== 'EN') dir="rtl" @endif>
                            <b>{{ $lang }}</b>
                            @if (in_array(strtolower($lang), $r['missing'], true))
                                <em class="aram-sc-hit__gap">missing</em>
                            @else
                                {{ Str::limit($value, 110) }}
                            @endif
                        </span>
                    @endforeach
                </a>
            @empty
                <p class="aram-msg__empty">
                    {{ $this->missing ? 'Every text has its Kurdish and Arabic.' : 'No text has these words.' }}
                </p>
            @endforelse
        </div>
    @else
        {{-- The part of the site, then its sections --}}
        <x-filament::tabs label="Parts of the site">
            @foreach ($groups as $group => $sections)
                <x-filament::tabs.item tag="a" :href="static::getUrl(['section' => array_key_first($sections)])" :active="$group === $currentGroup">
                    {{ $group }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
        <div class="aram-sc-sections">
            @foreach ($groups[$currentGroup] as $slug => $name)
                <a href="{{ static::getUrl(['section' => $slug]) }}" @class(['aram-sc-chip', 'is-active' => $this->section === $slug])>{{ $name }}</a>
            @endforeach
        </div>

        <form wire:submit="save" class="aram-form">
            {{ $this->form }}
            <div class="aram-form-actions">
                <span class="aram-form-actions__note">Saves this section only</span>
                <x-filament::button type="submit" size="lg" icon="heroicon-o-check">Save changes</x-filament::button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
