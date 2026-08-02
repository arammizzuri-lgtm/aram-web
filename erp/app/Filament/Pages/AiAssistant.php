<?php

namespace App\Filament\Pages;

use App\Services\Ai\AiProvider;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Ask the business a question in plain language.
 *
 * Answers are built only from the fixed tool surface, and every answer shows
 * which tools produced it — a figure nobody can trace is a figure nobody should
 * act on.
 */
class AiAssistant extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Ask';

    protected static ?string $title = 'Ask your business';

    protected string $view = 'filament.pages.ai-assistant';

    public string $question = '';

    /** @var array<int, array{role: string, text: string, tools: array<int, string>, error: bool}> */
    public array $transcript = [];

    public bool $thinking = false;

    /** @var array<int, array{role: string, content: mixed}> */
    public array $history = [];

    public function isConfigured(): bool
    {
        return app(AiProvider::class)->isConfigured();
    }

    /**
     * Starting questions, matched to what this user is allowed to be told.
     *
     * Offering "what was my profit?" to someone whose tools cannot return
     * profit would produce a confident non-answer, which is worse than not
     * offering it — so the cost-bearing suggestions appear only alongside the
     * cost-bearing tools.
     *
     * @return array<int, string>
     */
    public function suggestions(): array
    {
        $base = [
            'Which orders am I still waiting on?',
            'Which customers owe me the most money right now?',
            'Where are my goods at the moment?',
        ];

        if (auth()->user()?->can('view_cost')) {
            array_unshift($base, 'What was my profit over the last 30 days?');
            $base[] = 'Which orders lost me money?';
            $base[] = 'How much do I still owe my suppliers?';
        }

        return $base;
    }

    public function askSuggestion(string $question): void
    {
        $this->question = $question;
        $this->ask();
    }

    public function ask(): void
    {
        $question = trim($this->question);

        if ($question === '') {
            return;
        }

        $this->transcript[] = ['role' => 'user', 'text' => $question, 'tools' => [], 'error' => false];
        $this->history[] = ['role' => 'user', 'content' => $question];
        $this->question = '';
        $this->thinking = true;

        $answer = app(AiProvider::class)->ask($this->history, auth()->user());

        $this->thinking = false;

        if (! $answer->succeeded()) {
            $this->transcript[] = ['role' => 'assistant', 'text' => $answer->error, 'tools' => [], 'error' => true];
            // Drop the failed turn so the next question starts from clean history.
            array_pop($this->history);

            return;
        }

        $this->history[] = ['role' => 'assistant', 'content' => $answer->text];

        $this->transcript[] = [
            'role' => 'assistant',
            'text' => $answer->text,
            'tools' => $answer->toolTrail(),
            'error' => false,
        ];
    }

    public function clearConversation(): void
    {
        $this->reset(['transcript', 'history', 'question']);
    }
}
