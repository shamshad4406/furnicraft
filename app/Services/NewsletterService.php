<?php

namespace App\Services;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

/**
 * The mailing list.
 *
 * Double opt-in by default, because the footer box is open to the world and
 * somebody typing a stranger's address into it should not sign that stranger
 * up. A shop that skips this ends up on blocklists.
 *
 * Re-subscribing an address that unsubscribed is deliberate rather than
 * automatic: it only happens when somebody fills the form in again, which is
 * them asking.
 */
class NewsletterService
{
    /**
     * @return array{subscriber: NewsletterSubscriber, status: string}
     *         status: 'new', 'confirm_resent', 'already', 'resubscribed'
     */
    public function subscribe(string $email, array $details = []): array
    {
        $email = mb_strtolower(trim($email));

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);

        $requiresConfirmation = settings()->bool('newsletter_double_optin', true);

        // Already on the list and happy: say so rather than sending another
        // email they did not ask for.
        if ($subscriber->exists && $subscriber->isSubscribed()) {
            return ['subscriber' => $subscriber, 'status' => 'already'];
        }

        $wasUnsubscribed = $subscriber->exists && $subscriber->status === 'unsubscribed';

        $subscriber->fill([
            'name' => $details['name'] ?? $subscriber->name,
            'source' => $details['source'] ?? $subscriber->source ?? 'footer',
            'customer_id' => $details['customer_id'] ?? $subscriber->customer_id,
            'ip_address' => $details['ip_address'] ?? null,
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
        ]);

        if ($requiresConfirmation) {
            $subscriber->status = 'pending';
            $subscriber->confirm_token = Str::lower(Str::random(40));
            $subscriber->confirmed_at = null;
        } else {
            $subscriber->status = 'subscribed';
            $subscriber->confirm_token = null;
            $subscriber->confirmed_at = now();
        }

        $subscriber->save();

        return [
            'subscriber' => $subscriber->fresh(),
            'status' => match (true) {
                ! $requiresConfirmation && $wasUnsubscribed => 'resubscribed',
                $requiresConfirmation => 'confirm_resent',
                default => 'new',
            },
        ];
    }

    /**
     * They clicked the link in the confirmation email.
     */
    public function confirm(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::where('confirm_token', $token)->first();

        if (! $subscriber) {
            return null;
        }

        $subscriber->update([
            'status' => 'subscribed',
            'confirmed_at' => $subscriber->confirmed_at ?? now(),
            'confirm_token' => null,
        ]);

        return $subscriber->fresh();
    }

    /**
     * One click, no questions, no sign-in. Anything else is a dark pattern
     * and reliably produces spam complaints instead of unsubscribes.
     */
    public function unsubscribe(NewsletterSubscriber $subscriber, ?string $reason = null): NewsletterSubscriber
    {
        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
            'unsubscribe_reason' => $reason,
        ]);

        return $subscriber->fresh();
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $byStatus = NewsletterSubscriber::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'subscribed' => (int) $byStatus->get('subscribed', 0),
            'pending' => (int) $byStatus->get('pending', 0),
            'unsubscribed' => (int) $byStatus->get('unsubscribed', 0),
            'thisMonth' => NewsletterSubscriber::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }
}
