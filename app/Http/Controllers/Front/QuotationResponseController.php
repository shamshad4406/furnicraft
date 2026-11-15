<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Mail\QuotationAnswered;
use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The customer's copy of a quotation, and the two buttons on it.
 *
 * Reached by the token in their email, with no sign-in: most people being
 * quoted for custom furniture have no account, and making them create one to
 * say yes is how a sale goes quiet.
 *
 * Accepting is not a payment and commits nobody to anything irreversible —
 * staff turn an accepted quotation into an order, and the customer pays
 * against the invoice. That is why a token is enough here when it would not
 * be enough to cancel an order.
 */
class QuotationResponseController extends Controller
{
    public function __construct(protected QuotationService $quotations) {}

    public function show(string $token): View
    {
        $quotation = Quotation::with('items')->where('public_token', $token)->firstOrFail();

        // A draft was never sent, so as far as the world is concerned it
        // does not exist.
        abort_if($quotation->status === 'draft', 404);

        return view('front.quotation', [
            'quotation' => $quotation,
            'taxSummary' => $this->quotations->taxSummary($quotation),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $quotation = $this->answerable($token);

        if (! $quotation) {
            return redirect()->route('quotation.show', $token)
                ->with('error', 'This quotation can no longer be accepted. Please get in touch and we will send a fresh one.');
        }

        $data = $request->validate([
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->quotations->accept($quotation, $data['customer_note'] ?? null);

        $this->tellStaff($quotation->fresh(), 'accepted');

        return redirect()->route('quotation.show', $token)
            ->with('success', 'Thank you — we have your acceptance and will be in touch to arrange everything.');
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $quotation = $this->answerable($token);

        if (! $quotation) {
            return redirect()->route('quotation.show', $token)
                ->with('error', 'This quotation can no longer be answered.');
        }

        $data = $request->validate([
            'decline_reason' => ['nullable', 'string', 'max:300'],
        ]);

        $this->quotations->decline($quotation, $data['decline_reason'] ?? null);

        $this->tellStaff($quotation->fresh(), 'declined');

        return redirect()->route('quotation.show', $token)
            ->with('success', 'Thank you for letting us know.');
    }

    /**
     * The quotation if it is still open to an answer, else null.
     */
    protected function answerable(string $token): ?Quotation
    {
        $quotation = Quotation::where('public_token', $token)->firstOrFail();

        return $quotation->isAnswerable() ? $quotation : null;
    }

    /**
     * Somebody has to act on this, and they will not be watching the screen.
     */
    protected function tellStaff(Quotation $quotation, string $answer): void
    {
        $to = settings('company_email');

        if (blank($to)) {
            return;
        }

        try {
            Mail::to($to)->send(new QuotationAnswered($quotation, $answer));
        } catch (\Throwable $e) {
            Log::error('Quotation answer notification failed', [
                'quotation' => $quotation->number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
