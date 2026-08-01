<?php

namespace App\Services\Notifications;

/** One thing worth telling somebody about, and what they can do about it. */
final readonly class BusinessAlert
{
    public function __construct(
        public string $key,
        public string $title,
        public string $body,
        public string $severity,
        public string $url,
        public string $actionLabel,
    ) {}

    public function colour(): string
    {
        return match ($this->severity) {
            'danger' => 'danger',
            'warning' => 'warning',
            default => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this->severity) {
            'danger' => 'heroicon-o-exclamation-circle',
            'warning' => 'heroicon-o-exclamation-triangle',
            default => 'heroicon-o-information-circle',
        };
    }
}
