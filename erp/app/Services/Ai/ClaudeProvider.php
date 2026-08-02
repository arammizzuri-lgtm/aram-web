<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\Company;
use App\Models\User;
use Throwable;

/**
 * Runs the assistant loop against the Claude API.
 *
 * The loop is deliberately hand-written rather than delegated to a runner: every
 * tool call is routed through ErpToolSurface, which re-checks the caller's
 * permissions, and every call is recorded so the answer can show its working.
 */
class ClaudeProvider implements AiProvider
{
    /** Enough for a question that needs several lookups, not enough to loop forever. */
    private const int MAX_ITERATIONS = 6;

    public function __construct(private readonly ErpToolSurface $tools) {}

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function ask(array $messages, User $user): AiAnswer
    {
        if (! $this->isConfigured()) {
            return AiAnswer::failed(
                'No Claude API key is configured. Add ANTHROPIC_API_KEY to your .env file to enable the assistant.'
            );
        }

        $client = new Client(apiKey: config('services.anthropic.key'));
        $definitions = $this->tools->definitions($user);
        $calls = [];
        $inputTokens = 0;
        $outputTokens = 0;

        try {
            for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
                $response = $client->messages->create(
                    maxTokens: 4096,
                    messages: $messages,
                    model: config('services.anthropic.model', 'claude-opus-5'),
                    system: $this->systemPrompt($user),
                    thinking: ['type' => 'adaptive'],
                    tools: $definitions,
                );

                $inputTokens += $response->usage->inputTokens ?? 0;
                $outputTokens += $response->usage->outputTokens ?? 0;

                // Safety classifiers can decline; check before reading content.
                if (($response->stopReason ?? null) === 'refusal') {
                    return AiAnswer::failed('The model declined to answer that question.');
                }

                $blocks = $this->normalise($response->content ?? []);
                $toolUses = array_values(array_filter($blocks, fn ($b) => ($b['type'] ?? null) === 'tool_use'));

                if ($toolUses === []) {
                    return new AiAnswer(
                        text: $this->textOf($blocks),
                        toolCalls: $calls,
                        inputTokens: $inputTokens,
                        outputTokens: $outputTokens,
                    );
                }

                $messages[] = ['role' => 'assistant', 'content' => $blocks];

                $results = [];

                foreach ($toolUses as $use) {
                    $input = (array) ($use['input'] ?? []);
                    $calls[] = ['name' => $use['name'], 'input' => $input];

                    $results[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $use['id'],
                        'content' => json_encode(
                            $this->tools->run($use['name'], $input, $user),
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        ),
                    ];
                }

                // All results go back in one user message — splitting them
                // trains the model out of making parallel calls.
                $messages[] = ['role' => 'user', 'content' => $results];
            }

            return AiAnswer::failed('The assistant used too many steps without reaching an answer. Try a narrower question.');
        } catch (Throwable $e) {
            report($e);

            return AiAnswer::failed('Could not reach the Claude API: '.$e->getMessage());
        }
    }

    /** @param array<int, mixed> $content */
    private function normalise(array $content): array
    {
        return array_map(
            fn ($block) => is_array($block) ? $block : json_decode(json_encode($block), true),
            $content,
        );
    }

    private function textOf(array $blocks): string
    {
        return trim(implode("\n\n", array_map(
            fn ($b) => $b['text'] ?? '',
            array_filter($blocks, fn ($b) => ($b['type'] ?? null) === 'text'),
        )));
    }

    private function systemPrompt(User $user): string
    {
        $company = Company::current()?->name ?? config('app.name');
        $restriction = $user->can('view_cost')
            ? ''
            : "\n\nThis user is not permitted to see cost, margin or supplier pricing. "
                .'Those tools are not available to you. If asked, say plainly that cost '
                .'figures are restricted to management — do not estimate or infer them.';

        return <<<PROMPT
            You are the analyst for {$company}, an import and wholesale business that buys
            from Chinese suppliers and sells to local shops.

            Answer questions using the tools provided. Never guess a figure — if a tool
            cannot give you the number, say so.

            What matters in this business:
            - "Cost" always means landed cost: the supplier price plus freight, insurance,
              customs duty and clearance, allocated to each item. Never quote the supplier
              price as if it were the cost.
            - A container whose costing status is not "Final" is still provisional. Say so
              whenever you quote a margin that depends on it.
            - Money is USD unless stated. Format as \$1,234.56, and use a minus sign for
              negatives.

            Be direct and brief. Lead with the answer, then the supporting numbers. Use a
            short table when comparing several rows. Do not pad with caveats.{$restriction}
            PROMPT;
    }
}
