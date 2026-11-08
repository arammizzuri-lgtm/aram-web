<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The weekly report (App\Support\WeeklyReport): last week on the website. */
class WeeklyReportMail extends Mailable
{
    /** @param  array<string, mixed>  $report */
    public function __construct(public array $report) {}

    public function envelope(): Envelope
    {
        $r = $this->report;
        $enquiries = $r['enquiryList']->count();

        return new Envelope(
            subject: 'Your week on the website — '.$r['visitors'][0].' visitors, '.$enquiries.' '.($enquiries === 1 ? 'enquiry' : 'enquiries')
                .' ('.$r['from']->format('j M').' – '.$r['to']->format('j M').')',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.weekly-report', with: ['r' => $this->report]);
    }
}
