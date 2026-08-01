<?php

namespace App\Services\Ai;

use App\Models\User;

/**
 * The seam between the ERP and whichever model answers its questions.
 *
 * Kept as a contract so the assistant can be pointed at a different provider,
 * or stubbed entirely in tests, without the Filament page or the tool surface
 * knowing anything about it.
 */
interface AiProvider
{
    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     */
    public function ask(array $messages, User $user): AiAnswer;

    /** Whether the provider is configured well enough to be used. */
    public function isConfigured(): bool;
}
