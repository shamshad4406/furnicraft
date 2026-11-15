<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once an order is confirmed — after payment, or straight away for a
 * cash-on-delivery order that needs no advance.
 */
class OrderConfirmation extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $company = (string) settings('company_name', 'FurniCraft');

        return new Envelope(
            subject: $company.' order '.$this->order->order_number.' confirmed',
            replyTo: array_filter([settings('company_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.confirmation',
            with: [
                'order' => $this->order->loadMissing('items'),
                'company' => settings('company_name', 'FurniCraft'),
                'supportEmail' => settings('company_email'),
                'supportPhone' => settings('company_phone'),
            ],
        );
    }
}
