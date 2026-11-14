<?php

namespace App\Mail;

use App\Filament\Resources\VisitRequests\VisitRequestResource;
use App\Models\VisitRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the studio someone asked for a visit on the website, to confirm or move in the admin. */
class VisitRequested extends Mailable
{
    public function __construct(public VisitRequest $visit) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: VisitRequest::KINDS[$this->visit->kind].' asked for by '.$this->visit->name
            .' — '.$this->visit->wanted_on->format('D j M').', '.$this->visit->wanted_time);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.visit-requested', with: ['adminUrl' => VisitRequestResource::getUrl('index')]);
    }
}
