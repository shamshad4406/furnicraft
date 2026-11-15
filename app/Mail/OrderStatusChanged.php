<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the customer their order has moved on.
 *
 * One mailable for every status rather than four near-identical ones: the
 * difference between "shipped" and "delivered" is a sentence and a subject
 * line, and four files would drift apart the first time someone edits one.
 */
class OrderStatusChanged extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public string $status,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        $company = (string) settings('company_name', 'FurniCraft');
        $number = $this->order->order_number;

        $subject = match ($this->status) {
            'confirmed' => $company.' order '.$number.' confirmed',
            'shipped' => 'Your '.$company.' order '.$number.' is on its way',
            'delivered' => $company.' order '.$number.' delivered',
            'cancelled' => $company.' order '.$number.' cancelled',
            default => 'Update on your '.$company.' order '.$number,
        };

        return new Envelope(
            subject: $subject,
            replyTo: array_filter([settings('company_email')]),
        );
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing(['items', 'shipments']);

        return new Content(
            markdown: 'emails.orders.status-changed',
            with: [
                'order' => $order,
                'status' => $this->status,
                'note' => $this->note,
                'shipment' => $order->shipments->firstWhere('status', '!=', 'pending') ?? $order->shipments->first(),
                'headline' => $this->headline(),
                'company' => settings('company_name', 'FurniCraft'),
                'supportPhone' => settings('company_phone'),
                // A guest has no account, so their way back is the token link.
                'trackUrl' => $order->customer_id
                    ? route('account.order', $order->public_token)
                    : route('track.status', $order->public_token),
            ],
        );
    }

    protected function headline(): string
    {
        return match ($this->status) {
            'confirmed' => 'Your order is confirmed',
            'processing' => 'We are preparing your order',
            'shipped' => 'Your order is on its way',
            'delivered' => 'Your order has been delivered',
            'cancelled' => 'Your order has been cancelled',
            'returned' => 'Your return has been received',
            default => 'An update on your order',
        };
    }
}
