<?php

namespace App\Mail;

use App\Models\OrderReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the customer what happened to the return they raised.
 */
class ReturnUpdated extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public OrderReturn $return) {}

    public function envelope(): Envelope
    {
        $company = (string) settings('company_name', 'FurniCraft');

        $subject = match ($this->return->status) {
            'approved' => 'Your return '.$this->return->reference.' is approved',
            'rejected' => 'About your return '.$this->return->reference,
            'refunded' => 'Refund sent for '.$this->return->reference,
            'replaced' => 'Replacement on its way for '.$this->return->reference,
            default => 'Update on your return '.$this->return->reference,
        };

        return new Envelope(
            subject: $company.' — '.$subject,
            replyTo: array_filter([settings('company_email')]),
        );
    }

    public function content(): Content
    {
        $return = $this->return->loadMissing(['order', 'items.orderItem']);

        return new Content(
            markdown: 'emails.returns.updated',
            with: [
                'return' => $return,
                'order' => $return->order,
                'company' => settings('company_name', 'FurniCraft'),
                'supportPhone' => settings('company_phone'),
                'trackUrl' => $return->order->customer_id
                    ? route('account.order', $return->order->public_token)
                    : route('track.status', $return->order->public_token),
            ],
        );
    }
}
