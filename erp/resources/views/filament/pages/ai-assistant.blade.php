<x-filament-panels::page>
    @unless ($this->isConfigured())
        {{-- `--erp-status-warning` was never a token in this system, so this
             border resolved to nothing at all. The token is `--erp-warning`. --}}
        <div class="erp-card p-4"
             style="border-inline-start: 3px solid var(--erp-warning)">
            <div class="text-sm font-semibold">The assistant needs a Claude API key</div>
            <div class="mt-1 text-xs" style="color: var(--erp-text-secondary)">
                Add <code>ANTHROPIC_API_KEY=…</code> to your <code>.env</code> file and restart the
                server. Everything else on this page works as soon as it is set.
            </div>
        </div>
    @endunless

    <x-filament::section>
        <x-slot name="heading">Ask a question</x-slot>
        <x-slot name="description">
            Answers are built only from your own data, and each one lists the lookups behind it.
        </x-slot>

        <div class="flex gap-2">
            <input
                type="text"
                wire:model="question"
                wire:keydown.enter="ask"
                placeholder="e.g. Which products are losing me money?"
                @disabled(! $this->isConfigured())
                class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />

            <x-filament::button wire:click="ask" wire:loading.attr="disabled" :disabled="! $this->isConfigured()">
                <span wire:loading.remove wire:target="ask,askSuggestion">Ask</span>
                <span wire:loading wire:target="ask,askSuggestion">Thinking…</span>
            </x-filament::button>

            @if (filled($transcript))
                <x-filament::button wire:click="clearConversation" color="gray">Clear</x-filament::button>
            @endif
        </div>

        @if (empty($transcript))
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($this->suggestions() as $suggestion)
                    <button
                        wire:click="askSuggestion(@js($suggestion))"
                        @disabled(! $this->isConfigured())
                        class="rounded-full border px-3 py-1.5 text-xs transition hover:opacity-80 disabled:opacity-50"
                        style="border-color: var(--erp-border); color: var(--erp-text-secondary)">
                        {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    @if (filled($transcript))
        <x-filament::section>
            <x-slot name="heading">Conversation</x-slot>

            <div class="space-y-5">
                @foreach ($transcript as $turn)
                    @if ($turn['role'] === 'user')
                        <div class="flex justify-end">
                            <div class="max-w-2xl rounded-xl px-4 py-2.5 text-sm"
                                 style="background: var(--color-primary-600); color: white">
                                {{ $turn['text'] }}
                            </div>
                        </div>
                    @else
                        <div>
                            <div class="erp-card max-w-3xl px-4 py-3 text-sm"
                                 @style([
                                     'border-color: var(--erp-border)',
                                     'background: var(--erp-bg-surface)',
                                     'border-inline-start: 3px solid var(--erp-critical-text)' => $turn['error'],
                                 ])>
                                <div class="prose prose-sm max-w-none dark:prose-invert">
                                    {!! str($turn['text'])->markdown() !!}
                                </div>
                            </div>

                            {{-- The trail is what makes a number checkable. --}}
                            @if (filled($turn['tools']))
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs"
                                     style="color: var(--erp-text-muted)">
                                    <span>Built from:</span>
                                    @foreach ($turn['tools'] as $tool)
                                        <span class="rounded-md border px-2 py-0.5"
                                              style="border-color: var(--erp-border)">{{ $tool }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                @endforeach

                <div wire:loading wire:target="ask,askSuggestion" class="text-sm" style="color: var(--erp-text-muted)">
                    Looking through your data…
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
