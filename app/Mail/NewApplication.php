<?php

namespace App\Mail;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobApplication;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the studio someone has applied, with their CV attached so it can be
 * read straight from the inbox; "Reply" answers the applicant.
 */
class NewApplication extends Mailable
{
    public function __construct(public JobApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Job application — '.$this->application->name.' · '.$this->application->positionLabel(),
            replyTo: [new Address($this->application->email, $this->application->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-application',
            with: [
                'a' => $this->application,
                'adminUrl' => JobApplicationResource::getUrl('edit', ['record' => $this->application]),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return $this->application->cvExists()
            ? [Attachment::fromStorageDisk(JobApplication::DISK, $this->application->cv_path)
                ->as($this->application->cv_name ?: 'cv.pdf')->withMime('application/pdf')]
            : [];
    }
}
