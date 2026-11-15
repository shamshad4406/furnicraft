<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Mail\OrderStatusChanged;
use App\Mail\ReturnUpdated;
use App\Models\Order;
use App\Services\InvalidStatusTransition;
use App\Services\OrderWorkflow;
use App\Services\ReturnNotPossible;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * What a signed-in customer may do to their own order.
 *
 * Only two things, and both are deliberately narrow: call it off while it has
 * not been worked on yet, and ask to send something back once it has arrived.
 * Everything else is a conversation with staff.
 *
 * Guests are not covered here. Giving a cancel button to anyone holding a
 * tracking link would let a forwarded email cancel somebody's sofa.
 */
class OrderActionController extends Controller
{
    public function __construct(
        protected OrderWorkflow $workflow,
        protected ReturnService $returns,
    ) {}

    public function cancel(Request $request, string $token): RedirectResponse
    {
        $order = $this->ownOrder($token);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:300'],
        ], [
            'reason.required' => 'Please tell us why you are cancelling.',
        ]);

        if (! $order->isCancellable()) {
            return back()->with('error',
                'This order is already being prepared, so it cannot be cancelled here. Please call us and we will sort it out.');
        }

        try {
            $this->workflow->moveTo(
                $order,
                'cancelled',
                'Cancelled by the customer: '.$data['reason'],
                actor: null,
                notifyCustomer: false,
            );
        } catch (InvalidStatusTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->email($order->fresh(), fn (Order $fresh) => new OrderStatusChanged(
            $fresh,
            'cancelled',
            $fresh->amount_paid > 0 ? 'Your refund is being processed.' : null,
        ));

        return back()->with('success', 'Your order has been cancelled.'
            .($order->amount_paid > 0 ? ' Any amount paid will be refunded to the way you paid.' : ''));
    }

    public function returnForm(string $token): View
    {
        $order = $this->ownOrder($token);

        abort_unless($order->isReturnable(), 404);

        return view('front.account.return-request', [
            'order' => $order,
            'items' => $this->returns->returnableItems($order),
            'types' => \App\Models\OrderReturn::TYPES,
            'reasons' => \App\Models\OrderReturn::REASONS,
        ]);
    }

    public function requestReturn(Request $request, string $token): RedirectResponse
    {
        $order = $this->ownOrder($token);

        if (! $order->isReturnable()) {
            $window = (int) settings('return_window_days', 7);

            return back()->with('error', $window > 0
                ? 'The '.$window.'-day return window for this order has closed. Please call us if something is wrong.'
                : 'Returns are not available online. Please call us and we will help.');
        }

        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'type' => ['required', Rule::in(array_keys(\App\Models\OrderReturn::TYPES))],
            'reason' => ['required', Rule::in(array_keys(\App\Models\OrderReturn::REASONS))],
            'details' => ['nullable', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Please choose what went wrong.',
        ]);

        try {
            $return = $this->returns->request($order, $data['quantities'], $data);
        } catch (ReturnNotPossible $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->email($order, fn () => new ReturnUpdated($return->fresh(['order', 'items.orderItem'])));

        return redirect()->route('account.order', $order->public_token)
            ->with('success', 'We have your request ('.$return->reference.'). Our team will be in touch shortly.');
    }

    /**
     * The signed-in customer's own order, or a 404. Never a token lookup:
     * acting on an order has to be the account holder, not whoever has the
     * link.
     */
    protected function ownOrder(string $token): Order
    {
        return auth('customer')->user()
            ->orders()
            ->with('items')
            ->where('public_token', $token)
            ->firstOrFail();
    }

    /**
     * @param  callable(Order): \Illuminate\Mail\Mailable  $build
     */
    protected function email(Order $order, callable $build): void
    {
        try {
            Mail::to($order->customer_email)->send($build($order));
        } catch (\Throwable $e) {
            // The action succeeded; only the email did not. Never surface
            // this as a failure to the customer.
            Log::error('Customer order action email failed', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
