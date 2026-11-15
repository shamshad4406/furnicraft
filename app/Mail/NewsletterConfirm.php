<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Did you mean to sign up?" — the second half of double opt-in, so that
 * somebody typing a stranger's address into the footer does not sign that
 * stranger up.
 */
class NewsletterConfirm extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your subscription to '.settings('company_name', 'FurniCraft'),
            replyTo: array_filter([settings('company_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.newsletter.confirm',
            with: [
                'subscriber' => $this->subscriber,
                'company' => settings('company_name', 'FurniCraft'),
                'confirmUrl' => route('newsletter.confirm', $this->subscriber->confirm_token),
            ],
        );
    }
}
