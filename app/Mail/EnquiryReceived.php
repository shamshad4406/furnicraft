<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "We have your enquiry." Sent the moment one arrives, so nobody is left
 * wondering whether the form worked.
 */
class EnquiryReceived extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        $company = (string) settings('company_name', 'FurniCraft');

        return new Envelope(
            subject: $company.' — we have your enquiry '.$this->enquiry->reference,
            replyTo: array_filter([settings('company_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.enquiries.received',
            with: [
                'enquiry' => $this->enquiry->loadMissing('product'),
                'company' => settings('company_name', 'FurniCraft'),
                'supportPhone' => settings('company_phone'),
            ],
        );
    }
}
