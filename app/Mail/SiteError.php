<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "The website had an error": what broke, where, and on which page, for the
 * studio — or whoever looks after the site — to act on (App\Support\SiteAlerts).
 */
class SiteError extends Mailable
{
    public function __construct(
        public string $error,
        public string $type,
        public string $where,
        public ?string $url,
        public ?string $method,
        public ?string $referrer,
        public string $agent,
        public string $at,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Website error — '.mb_substr($this->error, 0, 80));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.site-error');
    }
}
