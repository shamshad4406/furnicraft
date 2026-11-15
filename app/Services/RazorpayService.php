<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use RuntimeException;

/**
 * Razorpay, kept behind one class so the rest of the app never touches the
 * SDK or the secret.
 *
 * The rule throughout: nothing is treated as paid until a signature computed
 * with our secret matches what Razorpay sent. A browser callback alone is
 * never trusted.
 */
class RazorpayService
{
    public function configured(): bool
    {
        return filled(config('services.razorpay.key')) && filled(config('services.razorpay.secret'));
    }

    public function publicKey(): ?string
    {
        return config('services.razorpay.key');
    }

    public function isTestMode(): bool
    {
        return str_starts_with((string) $this->publicKey(), 'rzp_test_');
    }

    protected function api(): Api
    {
        if (! $this->configured()) {
            throw new RuntimeException('Razorpay is not configured. Set RAZORPAY_KEY and RAZORPAY_SECRET.');
        }

        return new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    /**
     * Create the gateway order. The amount comes from our own totals, never
     * from anything the browser submitted.
     *
     * @return array{id: string, amount: int, currency: string}
     */
    public function createOrder(Order $order, int $amountInPaise): array
    {
        $created = $this->api()->order->create([
            'amount' => $amountInPaise,
            'currency' => config('services.razorpay.currency', 'INR'),
            'receipt' => $order->order_number,
            'notes' => [
                'order_number' => $order->order_number,
                'customer_email' => $order->customer_email,
            ],
        ]);

        return [
            'id' => $created['id'],
            'amount' => (int) $created['amount'],
            'currency' => $created['currency'],
        ];
    }

    /**
     * Verify the handshake the browser returns after a payment.
     *
     * signature = HMAC_SHA256(razorpay_order_id + "|" + razorpay_payment_id, secret)
     */
    public function verifyPaymentSignature(string $razorpayOrderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac(
            'sha256',
            $razorpayOrderId.'|'.$paymentId,
            (string) config('services.razorpay.secret')
        );

        return hash_equals($expected, $signature);
    }

    /**
     * Verify a webhook body against the webhook secret from the dashboard.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('services.razorpay.webhook_secret');

        if (blank($secret)) {
            // Refuse rather than accept unverified money events.
            Log::warning('Razorpay webhook received but RAZORPAY_WEBHOOK_SECRET is not set; ignoring.');

            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /**
     * Ask Razorpay what it thinks the payment is, rather than believing the
     * browser. Used as the final check before marking an order paid.
     *
     * @return array<string, mixed>|null
     */
    public function fetchPayment(string $paymentId): ?array
    {
        try {
            return $this->api()->payment->fetch($paymentId)->toArray();
        } catch (\Throwable $e) {
            Log::error('Razorpay payment fetch failed', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Send money back through the gateway.
     *
     * Deliberately loud rather than forgiving: a refund that fails and is
     * recorded as done is worse than one that fails noisily, because nobody
     * finds out until the customer chases it. The caller catches this and
     * leaves the return un-refunded.
     *
     * Razorpay is itself idempotent on `receipt`, so a double-click returns
     * the refund already made instead of sending the money twice.
     *
     * @return array<string, mixed>
     *
     * @throws RefundFailed
     */
    public function refund(string $paymentId, float $amount, string $receipt, array $notes = []): array
    {
        if (! $this->configured()) {
            throw new RefundFailed('Razorpay is not configured, so a refund cannot be sent from here.');
        }

        $paise = (int) round($amount * 100);

        if ($paise <= 0) {
            throw new RefundFailed('A refund has to be more than zero.');
        }

        try {
            $refund = $this->api()->payment->fetch($paymentId)->refund([
                'amount' => $paise,
                'speed' => 'normal',
                'receipt' => $receipt,
                'notes' => $notes,
            ])->toArray();
        } catch (\Throwable $e) {
            Log::error('Razorpay refund failed', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'receipt' => $receipt,
                'error' => $e->getMessage(),
            ]);

            throw new RefundFailed($this->readable($e->getMessage()), previous: $e);
        }

        Log::info('Razorpay refund sent', [
            'payment_id' => $paymentId,
            'refund_id' => $refund['id'] ?? null,
            'amount' => $amount,
        ]);

        return $refund;
    }

    /**
     * Gateway errors are written for developers. Put the common ones into
     * words the person at the screen can act on.
     */
    protected function readable(string $message): string
    {
        return match (true) {
            str_contains($message, 'fully refunded') => 'This payment has already been refunded in full.',
            str_contains($message, 'greater than') || str_contains($message, 'exceed') =>
                'That is more than is left to refund on this payment.',
            str_contains($message, 'not captured') =>
                'This payment was never captured, so there is nothing to refund.',
            str_contains($message, 'Authentication') || str_contains($message, 'authentication') =>
                'Razorpay refused the credentials. Check the keys in .env.',
            default => 'Razorpay refused the refund: '.$message,
        };
    }
}
