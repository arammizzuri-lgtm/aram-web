<?php

namespace App\Mail;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The alert the studio gets when an enquiry arrives: everything the visitor
 * said, with one-tap ways to answer — reply (straight to them), WhatsApp,
 * call — and a link to the enquiry in the admin.
 */
class NewEnquiry extends Mailable
{
    public function __construct(public ContactMessage $enquiry) {}

    public function envelope(): Envelope
    {
        $about = $this->enquiry->project?->name ?? $this->enquiry->typeLabel();

        return new Envelope(
            subject: 'New enquiry — '.$this->enquiry->name.($about ? ' · '.$about : ''),
            // "Reply" in the mail app answers the visitor, not the website
            replyTo: $this->enquiry->email ? [new Address($this->enquiry->email, $this->enquiry->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-enquiry',
            with: [
                'e' => $this->enquiry,
                'adminUrl' => ContactMessageResource::getUrl('edit', ['record' => $this->enquiry]),
            ],
        );
    }
}
