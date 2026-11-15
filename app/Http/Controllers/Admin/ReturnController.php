<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReturnUpdated;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Services\RazorpayService;
use App\Services\RefundFailed;
use App\Services\ReturnNotPossible;
use App\Services\ReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Returns live under the orders permission: whoever handles orders handles
 * what comes back from them, and a separate permission for the same people
 * would only be one more thing to forget to grant.
 */
class ReturnController extends Controller implements HasMiddleware
{
    public function __construct(
        protected ReturnService $returns,
        protected RazorpayService $razorpay,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.view', only: ['index', 'show']),
            new Middleware('permission:orders.edit', only: ['store', 'approve', 'reject', 'pickUp', 'receive', 'refund', 'replace', 'close']),
        ];
    }

    public function index(Request $request): View
    {
        $returns = OrderReturn::query()
            // public_token is the Order route key, so a trimmed select that
            // leaves it out breaks every link to the order.
            ->with(['order:id,public_token,order_number,customer_name,customer_email', 'resolver:id,name'])
            ->withCount('items')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('reference', 'like', $like)
                    ->orWhereHas('order', fn (Builder $o) => $o
                        ->where('order_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_email', 'like', $like))
                );
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->input('flag') === 'open', fn (Builder $q) => $q->whereNotIn('status', OrderReturn::FINAL))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.returns.index', [
            'returns' => $returns,
            'statuses' => OrderReturn::STATUSES,
            'types' => OrderReturn::TYPES,
            'openCount' => OrderReturn::whereNotIn('status', OrderReturn::FINAL)->count(),
        ]);
    }

    public function show(OrderReturn $return): View
    {
        $return->load(['order.items', 'items.orderItem', 'resolver:id,name', 'customer:id,name,email']);

        return view('admin.returns.show', [
            'return' => $return,
            'statuses' => OrderReturn::STATUSES,
            // Null when there is nothing for the gateway to reverse, which
            // is how the form knows not to offer it.
            'gatewayPayment' => $this->capturedOnlinePayment($return),
        ]);
    }

    /**
     * Staff raising a return on the customer's behalf — the usual case, since
     * most people phone rather than fill in a form.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'type' => ['required', Rule::in(array_keys(OrderReturn::TYPES))],
            'reason' => ['required', Rule::in(array_keys(OrderReturn::REASONS))],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $return = $this->returns->request($order, $data['quantities'], $data);
        } catch (ReturnNotPossible $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.returns.show', $return)
            ->with('success', 'Return '.$return->reference.' raised.');
    }

    public function approve(Request $request, OrderReturn $return): RedirectResponse
    {
        $data = $request->validate([
            'customer_message' => ['nullable', 'string', 'max:500'],
            'refund_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notify' => ['nullable', 'boolean'],
        ]);

        if (! $this->isAt($return, ['requested'])) {
            return back()->with('error', 'Only a requested return can be approved.');
        }

        $this->returns->approve(
            $return,
            auth('admin')->user(),
            $data['customer_message'] ?? null,
            isset($data['refund_amount']) ? (float) $data['refund_amount'] : null,
        );

        return back()->with('success', 'Return approved.'.$this->maybeNotify($request, $return->fresh()));
    }

    public function reject(Request $request, OrderReturn $return): RedirectResponse
    {
        $data = $request->validate([
            // Required: a refusal with no reason is the thing customers
            // complain about, and staff need it on the record too.
            'customer_message' => ['required', 'string', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ], [
            'customer_message.required' => 'Say why this is being refused — the customer is told this.',
        ]);

        if (! $this->isAt($return, ['requested', 'approved'])) {
            return back()->with('error', 'This return has gone too far to be refused.');
        }

        $this->returns->reject($return, auth('admin')->user(), $data['customer_message']);

        return back()->with('success', 'Return refused.'.$this->maybeNotify($request, $return->fresh()));
    }

    public function pickUp(Request $request, OrderReturn $return): RedirectResponse
    {
        $data = $request->validate([
            'staff_note' => ['nullable', 'string', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ]);

        if (! $this->isAt($return, ['approved'])) {
            return back()->with('error', 'Only an approved return can be marked collected.');
        }

        $this->returns->markPickedUp($return, auth('admin')->user(), $data['staff_note'] ?? null);

        return back()->with('success', 'Marked as collected from the customer.'.$this->maybeNotify($request, $return->fresh()));
    }

    /**
     * For a claim settled without money or goods moving — advice given, a
     * part posted out. Otherwise it would sit open forever.
     */
    public function close(Request $request, OrderReturn $return): RedirectResponse
    {
        $data = $request->validate([
            'customer_message' => ['nullable', 'string', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ]);

        if (! $return->isOpen()) {
            return back()->with('error', 'This return is already settled.');
        }

        $this->returns->close($return, auth('admin')->user(), $data['customer_message'] ?? null);

        return back()->with('success', 'Return closed.'.$this->maybeNotify($request, $return->fresh()));
    }

    public function receive(Request $request, OrderReturn $return): RedirectResponse
    {
        $request->validate([
            'restock' => ['nullable', 'array'],
            'restock.*' => ['nullable', 'boolean'],
        ]);

        if (! $this->isAt($return, ['approved', 'picked_up'])) {
            return back()->with('error', 'Approve the return before marking it received.');
        }

        $this->returns->receive(
            $return,
            auth('admin')->user(),
            array_map('boolval', $request->input('restock', [])),
        );

        return back()->with('success', 'Marked as received. Anything ticked has gone back into stock.');
    }

    public function refund(Request $request, OrderReturn $return): RedirectResponse
    {
        $data = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'refund_method' => ['required', Rule::in(['original', 'bank', 'store_credit'])],
            'refund_reference' => ['nullable', 'string', 'max:120'],
            'send_to_gateway' => ['nullable', 'boolean'],
            'notify' => ['nullable', 'boolean'],
        ]);

        if (! $this->isAt($return, ['approved', 'picked_up', 'received'])) {
            return back()->with('error', 'A refund can only be recorded once the return is approved.');
        }

        $amount = (float) $data['refund_amount'];
        $reference = $data['refund_reference'] ?? null;
        $sentByUs = false;

        // Actually send it, when asked and when there is a captured online
        // payment to send it back to.
        if ($request->boolean('send_to_gateway')) {
            $payment = $this->capturedOnlinePayment($return);

            if (! $payment) {
                return back()->withInput()->with('error',
                    'There is no captured online payment on this order, so Razorpay cannot send a refund. Record it by hand instead.');
            }

            try {
                $refund = $this->razorpay->refund($payment->gateway_payment_id, $amount, $return->reference, [
                    'order' => $return->order->order_number,
                    'return' => $return->reference,
                ]);
            } catch (RefundFailed $e) {
                // Nothing is recorded: a refund that did not happen must not
                // look like one that did.
                return back()->withInput()->with('error', $e->getMessage());
            }

            $reference = $refund['id'] ?? $reference;
            $sentByUs = true;
        }

        $this->returns->refund(
            $return,
            auth('admin')->user(),
            $amount,
            $data['refund_method'],
            $reference,
        );

        $message = $sentByUs
            ? 'Refund of '.money($amount).' sent through Razorpay ('.$reference.').'
            : 'Refund of '.money($amount).' recorded.';

        return back()->with('success', $message.$this->maybeNotify($request, $return->fresh()));
    }

    /**
     * The payment a gateway refund would go back to.
     *
     * Only a captured Razorpay payment qualifies: cash on delivery and
     * anything still pending have nothing for the gateway to reverse.
     */
    protected function capturedOnlinePayment(OrderReturn $return): ?Payment
    {
        return $return->order->payments()
            ->where('gateway', 'razorpay')
            ->where('status', 'captured')
            ->whereNotNull('gateway_payment_id')
            ->latest('id')
            ->first();
    }

    public function replace(Request $request, OrderReturn $return): RedirectResponse
    {
        $data = $request->validate([
            'customer_message' => ['nullable', 'string', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ]);

        if (! $this->isAt($return, ['approved', 'picked_up', 'received'])) {
            return back()->with('error', 'Approve the return before recording a replacement.');
        }

        $this->returns->markReplaced($return, auth('admin')->user(), $data['customer_message'] ?? null);

        return back()->with('success', 'Recorded as replaced.'.$this->maybeNotify($request, $return->fresh()));
    }

    /**
     * @param  string[]  $statuses
     */
    protected function isAt(OrderReturn $return, array $statuses): bool
    {
        return in_array($return->status, $statuses, true);
    }

    protected function maybeNotify(Request $request, OrderReturn $return): string
    {
        if (! $request->boolean('notify')) {
            return '';
        }

        try {
            Mail::to($return->order->customer_email)->send(new ReturnUpdated($return));

            return ' The customer has been emailed.';
        } catch (\Throwable $e) {
            Log::error('Return email failed', [
                'return' => $return->reference,
                'error' => $e->getMessage(),
            ]);

            return ' The customer could not be emailed — check the mail settings.';
        }
    }
}
