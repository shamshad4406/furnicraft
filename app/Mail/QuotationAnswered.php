<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells staff that a customer has answered a quotation.
 *
 * Goes to the shop, not the customer: an accepted quotation needs somebody
 * to turn it into an order, and nobody is watching the admin panel all day.
 */
class QuotationAnswered extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Quotation $quotation, public string $answer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Quotation '.$this->quotation->number.' '.$this->answer
                .' — '.$this->quotation->customer_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.quotations.answered',
            with: [
                'quotation' => $this->quotation,
                'answer' => $this->answer,
                'adminUrl' => route('admin.quotations.show', $this->quotation->public_token),
            ],
        );
    }
}
