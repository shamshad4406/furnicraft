<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay payment flow.
 *
 * The browser is never believed. It may *tell* us a payment succeeded, but the
 * order only becomes paid when a signature computed with our own secret
 * matches, and the payment is then confirmed against Razorpay's API.
 */
class PaymentController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected OrderService $orders,
        protected CartService $cart,
    ) {}

    /**
     * Create the gateway order and hand the browser what the checkout widget
     * needs. The amount is ours, not the browser's.
     */
    public function create(Request $request, string $token): JsonResponse
    {
        $order = $this->ownedOrder($request, $token);

        if ($order->payment_status === 'paid') {
            return response()->json(['error' => 'This order is already paid.'], 409);
        }

        $payable = $this->orders->amountPayableNow($order);

        if ($payable <= 0) {
            return response()->json(['error' => 'There is nothing to pay for this order.'], 422);
        }

        $amountInPaise = (int) round($payable * 100);

        // Reuse the gateway order if one was already created for this attempt,
        // so a refresh does not open a second payment.
        $existing = $order->payments()
            ->whereNotNull('gateway_order_id')
            ->where('status', 'created')
            ->latest('id')
            ->first();

        if ($existing && (int) round((float) $existing->amount * 100) === $amountInPaise) {
            $gatewayOrderId = $existing->gateway_order_id;
        } else {
            try {
                $created = $this->razorpay->createOrder($order, $amountInPaise);
            } catch (\Throwable $e) {
                Log::error('Razorpay order creation failed', [
                    'order' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['error' => 'Could not start the payment. Please try again.'], 502);
            }

            $gatewayOrderId = $created['id'];

            Payment::create([
                'order_id' => $order->id,
                'gateway' => 'razorpay',
                'gateway_order_id' => $gatewayOrderId,
                'amount' => $payable,
                'currency' => config('services.razorpay.currency', 'INR'),
                'status' => 'created',
            ]);
        }

        return response()->json([
            'key' => $this->razorpay->publicKey(),
            'order_id' => $gatewayOrderId,
            'amount' => $amountInPaise,
            'currency' => config('services.razorpay.currency', 'INR'),
            'name' => settings('company_name', 'FurniCraft'),
            'description' => 'Order '.$order->order_number,
            'prefill' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'contact' => $order->customer_phone,
            ],
            'callback_url' => route('payment.callback', $order),
        ]);
    }

    /**
     * The browser returns here after the widget closes. Verify, then confirm
     * with Razorpay before touching the order.
     */
    public function callback(Request $request, string $token): RedirectResponse
    {
        $order = $this->ownedOrder($request, $token);

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:120'],
            'razorpay_payment_id' => ['required', 'string', 'max:120'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        if ($order->payment_status === 'paid') {
            return redirect()->route('checkout.success', $order);
        }

        // 1. The handshake must be signed with our secret.
        $signatureValid = $this->razorpay->verifyPaymentSignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        if (! $signatureValid) {
            Log::warning('Razorpay signature mismatch', [
                'order' => $order->order_number,
                'payment_id' => $data['razorpay_payment_id'],
            ]);

            $this->orders->fail($order, 'Payment signature could not be verified');

            return redirect()->route('checkout.failed', $order);
        }

        // 2. Ask Razorpay what it actually holds, rather than trusting the post.
        $payment = $this->razorpay->fetchPayment($data['razorpay_payment_id']);

        if (! $payment) {
            // Verified signature but the API is unreachable: leave the order
            // pending and let the webhook settle it.
            Log::warning('Razorpay fetch failed after valid signature; awaiting webhook', [
                'order' => $order->order_number,
            ]);

            return redirect()->route('checkout.pay', $order)
                ->with('info', 'We are confirming your payment. This page will update shortly.');
        }

        $captured = in_array($payment['status'] ?? '', ['captured', 'authorized'], true);
        $paidAmount = ((int) ($payment['amount'] ?? 0)) / 100;
        $expected = $this->orders->amountPayableNow($order);

        // 3. The amount must match what we asked for.
        if ($captured && abs($paidAmount - $expected) > 0.01) {
            Log::error('Razorpay amount mismatch', [
                'order' => $order->order_number,
                'expected' => $expected,
                'received' => $paidAmount,
            ]);

            $this->orders->fail($order, 'Paid amount did not match the order total');

            return redirect()->route('checkout.failed', $order);
        }

        if (! $captured) {
            $this->orders->fail($order, $payment['error_description'] ?? 'Payment was not completed');

            return redirect()->route('checkout.failed', $order);
        }

        $this->orders->recordPayment(
            $order,
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
            $paidAmount,
            $payment,
        );

        $this->finishCheckout($request, $order);

        return redirect()->route('checkout.success', $order);
    }

    /**
     * The customer closed the widget or the payment failed in the browser.
     */
    public function cancelled(Request $request, string $token): RedirectResponse
    {
        $order = $this->ownedOrder($request, $token);

        if ($order->payment_status !== 'paid') {
            $this->orders->fail($order, $request->input('reason', 'Payment was not completed'));
        }

        return redirect()->route('checkout.failed', $order);
    }

    /**
     * Razorpay's server-to-server notification — the backup that settles an
     * order when the browser never came back.
     *
     * No session, no CSRF: the webhook signature is the only authentication.
     */
    public function webhook(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if ($signature === '' || ! $this->razorpay->verifyWebhookSignature($raw, $signature)) {
            Log::warning('Razorpay webhook rejected: bad signature');

            return response()->json(['status' => 'invalid signature'], 400);
        }

        $payload = json_decode($raw, true) ?: [];
        $event = $payload['event'] ?? 'unknown';
        $eventId = $request->header('X-Razorpay-Event-Id');
        $entity = $payload['payload']['payment']['entity'] ?? [];
        $paymentId = $entity['id'] ?? null;

        // Store first. A duplicate delivery is detected by the unique index.
        $record = PaymentWebhook::firstOrNew(['gateway_event_id' => $eventId]);

        if ($record->exists && $record->processed) {
            return response()->json(['status' => 'already processed']);
        }

        $record->fill([
            'event' => $event,
            'gateway_payment_id' => $paymentId,
            'payload' => $payload,
            'processed' => false,
        ])->save();

        $result = $this->handleWebhookEvent($event, $entity);

        $record->update(['processed' => true, 'result' => $result]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function handleWebhookEvent(string $event, array $entity): string
    {
        $paymentId = $entity['id'] ?? null;
        $gatewayOrderId = $entity['order_id'] ?? null;

        if (! $paymentId || ! $gatewayOrderId) {
            return 'ignored: no payment reference';
        }

        $payment = Payment::where('gateway_order_id', $gatewayOrderId)->first();
        $order = $payment?->order;

        if (! $order) {
            return 'ignored: no matching order';
        }

        $amount = ((int) ($entity['amount'] ?? 0)) / 100;

        if ($event === 'payment.captured' || $event === 'order.paid') {
            return $this->settleFromWebhook($order, $gatewayOrderId, $paymentId, $amount, $entity);
        }

        if ($event === 'payment.failed') {
            // A late failure for an order already paid by another attempt is
            // not a reason to undo the payment.
            if ($order->payment_status === 'paid') {
                return 'ignored: order already paid';
            }

            $this->orders->fail($order, $entity['error_description'] ?? 'Payment failed');

            return 'recorded failure';
        }

        return 'ignored: '.$event;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function settleFromWebhook(Order $order, string $gatewayOrderId, string $paymentId, float $amount, array $entity): string
    {
        if ($order->payment_status === 'paid') {
            return 'already paid';
        }

        $expected = $this->orders->amountPayableNow($order);

        if (abs($amount - $expected) > 0.01) {
            Log::error('Webhook amount mismatch', [
                'order' => $order->order_number,
                'expected' => $expected,
                'received' => $amount,
            ]);

            return 'amount mismatch';
        }

        // Signature is empty here: the webhook body signature already proved
        // authenticity, and it is recorded on the webhook row.
        $this->orders->recordPayment($order, $gatewayOrderId, $paymentId, '', $amount, $entity);

        return 'order marked paid';
    }

    protected function finishCheckout(Request $request, Order $order): void
    {
        $cart = $this->cart->current($request, false);

        if ($cart) {
            $this->cart->clear($cart);
        }

        $request->session()->forget('checkout_key');

        $guestOrders = (array) $request->session()->get('guest_order_ids', []);
        $guestOrders[] = $order->id;
        $request->session()->put('guest_order_ids', array_slice(array_unique($guestOrders), -10));
    }

    protected function ownedOrder(Request $request, string $token): Order
    {
        $order = Order::where('public_token', $token)->firstOrFail();

        $customerId = auth('customer')->id();

        $owns = ($customerId && $order->customer_id === $customerId)
            || $request->session()->get('current_order_id') === $order->id
            || in_array($order->id, (array) $request->session()->get('guest_order_ids', []), true);

        abort_unless($owns, 404);

        return $order;
    }
}
