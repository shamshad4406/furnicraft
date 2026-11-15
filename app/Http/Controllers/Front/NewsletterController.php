<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterConfirm;
use App\Models\NewsletterSubscriber;
use App\Services\NewsletterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    public function __construct(protected NewsletterService $newsletter) {}

    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'website' => ['prohibited'],
        ], [
            'email.required' => 'Please enter your email address.',
            'website.prohibited' => 'Something went wrong with that submission. Please try again.',
        ]);

        $customer = auth('customer')->user();

        $result = $this->newsletter->subscribe($data['email'], [
            'name' => $data['name'] ?? $customer?->name,
            'customer_id' => $customer?->id,
            'source' => $request->input('source', 'footer'),
            'ip_address' => $request->ip(),
        ]);

        if ($result['status'] === 'already') {
            return back()->with('success', 'You are already on the list — thank you.');
        }

        if (in_array($result['status'], ['confirm_resent'], true)) {
            $this->sendConfirmation($result['subscriber']);

            return back()->with('success',
                'Almost there — we have emailed you a link to confirm. Nothing is sent until you click it.');
        }

        return back()->with('success', 'You are on the list. Thank you.');
    }

    /**
     * They clicked the link in the confirmation email.
     */
    public function confirm(string $token): View
    {
        $subscriber = $this->newsletter->confirm($token);

        return view('front.newsletter', [
            'heading' => $subscriber ? 'You are on the list' : 'That link has expired',
            'message' => $subscriber
                ? 'Thank you for confirming. You will hear from us when there is something worth saying.'
                : 'This confirmation link has already been used, or it is no longer valid. Sign up again from the footer and we will send a fresh one.',
            'tone' => $subscriber ? 'success' : 'warning',
        ]);
    }

    /**
     * One click, no sign-in, no questions. Anything else produces spam
     * complaints instead of unsubscribes.
     */
    public function unsubscribe(Request $request, NewsletterSubscriber $subscriber): View
    {
        $this->newsletter->unsubscribe($subscriber, $request->input('reason'));

        return view('front.newsletter', [
            'heading' => 'You have been unsubscribed',
            'message' => 'We will not email you again. If it was a mistake, you can sign up again from the footer at any time.',
            'tone' => 'secondary',
        ]);
    }

    /**
     * A failed email must not lose the sign-up: they are already recorded as
     * pending, and can ask for the link again.
     */
    protected function sendConfirmation(NewsletterSubscriber $subscriber): void
    {
        try {
            Mail::to($subscriber->email)->send(new NewsletterConfirm($subscriber));
        } catch (\Throwable $e) {
            Log::error('Newsletter confirmation failed', [
                'email' => $subscriber->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
