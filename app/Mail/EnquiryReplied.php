<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A staff reply to an enquiry, sent as a plain message rather than a
 * template full of furniture — somebody wrote it by hand, so it should read
 * like a person answering a question.
 */
class EnquiryReplied extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Enquiry $enquiry, public string $body) {}

    public function envelope(): Envelope
    {
        $company = (string) settings('company_name', 'FurniCraft');

        return new Envelope(
            subject: 'Re: '.($this->enquiry->subject ?: 'your enquiry').' ['.$this->enquiry->reference.']',
            replyTo: array_filter([settings('company_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.enquiries.replied',
            with: [
                'enquiry' => $this->enquiry,
                'body' => $this->body,
                'company' => settings('company_name', 'FurniCraft'),
                'supportPhone' => settings('company_phone'),
            ],
        );
    }
}
