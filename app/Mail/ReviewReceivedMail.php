<?php

namespace App\Mail;

use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\ReviewRequest;
use App\Models\Testimonial;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the studio a client has sent their words through a review link. */
class ReviewReceivedMail extends Mailable
{
    public function __construct(public Testimonial $quote, public ReviewRequest $review) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New client quote — '.$this->quote->person_name.($this->review->project ? ' · '.$this->review->project->name : ''));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.review-received',
            with: [
                'q' => $this->quote,
                'r' => $this->review,
                'words' => $this->quote->{'quote_'.$this->review->lang},
                'adminUrl' => TestimonialResource::getUrl('edit', ['record' => $this->quote]),
            ],
        );
    }
}
