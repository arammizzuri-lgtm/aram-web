<?php

namespace App\Mail;

use App\Filament\Resources\Partners\PartnerResource;
use App\Models\Partner;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the studio a company has applied to become a partner, with a link
 * to the application in the admin; "Reply" answers the applicant.
 */
class PartnerApplicationMail extends Mailable
{
    public function __construct(public Partner $partner) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Partner application — '.$this->partner->name.' · '.Partner::categoryName($this->partner->category, 'en'),
            replyTo: [new Address($this->partner->email, $this->partner->contact_person ?: $this->partner->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.partner-application',
            with: [
                'p' => $this->partner,
                'adminUrl' => PartnerResource::getUrl('edit', ['record' => $this->partner]),
            ],
        );
    }
}
