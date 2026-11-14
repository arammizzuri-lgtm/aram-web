<?php

namespace App\Mail;

use App\Models\Task;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** The morning e-mail (App\Support\MorningTasks): what is late, and what is due today. */
class MorningTasksMail extends Mailable
{
    /**
     * @param  Collection<int, Task>  $late
     * @param  Collection<int, Task>  $today
     */
    public function __construct(public Collection $late, public Collection $today) {}

    public function envelope(): Envelope
    {
        $parts = array_filter([
            $this->today->count() ? $this->today->count().' due today' : null,
            $this->late->count() ? $this->late->count().' late' : null,
        ]);

        return new Envelope(subject: 'Your tasks — '.implode(', ', $parts).' ('.now(Task::TZ)->format('D j M').')');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.morning-tasks');
    }
}
