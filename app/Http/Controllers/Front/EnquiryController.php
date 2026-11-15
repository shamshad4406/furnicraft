<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\Product;
use App\Services\EnquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The public enquiry form.
 *
 * Open to everyone — most people asking about a sofa do not have an account
 * and should not need one. A signed-in customer gets their details filled in
 * and the enquiry tied to them.
 */
class EnquiryController extends Controller
{
    public function __construct(protected EnquiryService $enquiries) {}

    public function create(Request $request): View
    {
        // Arrives from "Enquiry Now" on a product card or detail page.
        $product = $request->filled('product')
            ? Product::where('slug', $request->string('product'))->active()->first()
            : null;

        $customer = auth('customer')->user();

        return view('front.enquiry', [
            'product' => $product,
            'customer' => $customer,
            'types' => Enquiry::TYPES,
            'defaultType' => $product ? 'product' : $request->string('type', 'general')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Enquiry::TYPES))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'required_by' => ['nullable', 'date', 'after_or_equal:today'],
            'product_slug' => ['nullable', 'string', 'max:255'],

            // Sketches and photos of the space, for a custom request.
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:8192',
            ],

            // Not a real field. A bot filling everything in gives itself away.
            'website' => ['prohibited'],
        ], [
            'message.min' => 'Please tell us a little more — a line or two is enough.',
            'pincode.regex' => 'Enter a valid 6-digit pincode.',
            'required_by.after_or_equal' => 'The date you need it by cannot be in the past.',
            'attachments.max' => 'Up to three images, please.',
            'attachments.*.image' => 'Attachments have to be images (JPG, PNG or WebP).',
            'website.prohibited' => 'Something went wrong with that submission. Please try again.',
        ]);

        // A second identical submission is nearly always an impatient second
        // click, not a second question.
        if ($this->enquiries->recentlySubmitted($data['email'])) {
            return redirect()->route('enquiry.create')
                ->with('success', 'We already have your enquiry — our team will be in touch shortly.');
        }

        $customer = auth('customer')->user();

        $product = filled($data['product_slug'] ?? null)
            ? Product::where('slug', $data['product_slug'])->first()
            : null;

        $enquiry = $this->enquiries->create([
            'type' => $data['type'],
            'customer_id' => $customer?->id,
            'product_id' => $product?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'city' => $data['city'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'quantity' => $data['quantity'] ?? null,
            'budget' => $data['budget'] ?? null,
            'required_by' => $data['required_by'] ?? null,
            'source' => 'web',
            'ip_address' => $request->ip(),
        ], $request->file('attachments', []));

        $this->acknowledge($enquiry);

        return redirect()->route('enquiry.sent', $enquiry->public_token);
    }

    /**
     * The thank-you page, addressed by token so a refresh is harmless and
     * the reference can be shown back.
     */
    public function sent(string $token): View
    {
        $enquiry = Enquiry::where('public_token', $token)->firstOrFail();

        return view('front.enquiry-sent', compact('enquiry'));
    }

    /**
     * Tell them we have it. A failure here must not lose the enquiry — it is
     * already saved, and staff can see it whether or not the email went.
     */
    protected function acknowledge(Enquiry $enquiry): void
    {
        try {
            Mail::to($enquiry->email)->send(new EnquiryReceived($enquiry));
        } catch (\Throwable $e) {
            Log::error('Enquiry acknowledgement failed', [
                'enquiry' => $enquiry->reference,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
