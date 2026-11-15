<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The quotation itself, with the link the customer answers it on.
 */
class QuotationSent extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Quotation $quotation) {}

    public function envelope(): Envelope
    {
        $company = (string) settings('company_name', 'FurniCraft');

        return new Envelope(
            subject: $company.' quotation '.$this->quotation->number,
            replyTo: array_filter([settings('company_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.quotations.sent',
            with: [
                'quotation' => $this->quotation->loadMissing('items'),
                'company' => settings('company_name', 'FurniCraft'),
                'supportPhone' => settings('company_phone'),
                'url' => route('quotation.show', $this->quotation->public_token),
            ],
        );
    }
}
