<?php

namespace App\Services\Ai;

/**
 * One answer, plus the tools it was built from.
 *
 * The tool trail is not decoration: a number the business will act on has to be
 * traceable back to the query that produced it, or the assistant is just a
 * confident stranger.
 */
final readonly class AiAnswer
{
    /**
     * @param  array<int, array{name: string, input: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public string $text,
        public array $toolCalls = [],
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $error = null,
    ) {}

    public static function failed(string $message): self
    {
        return new self(text: '', error: $message);
    }

    public function succeeded(): bool
    {
        return $this->error === null;
    }

    /** @return array<int, string> human-readable list of what was consulted */
    public function toolTrail(): array
    {
        return array_map(
            fn (array $call) => str($call['name'])->replace('_', ' ')->ucfirst()
                .(filled($call['input']) ? ' — '.json_encode($call['input']) : ''),
            $this->toolCalls,
        );
    }
}
