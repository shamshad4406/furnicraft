<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\QuotationSent;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\QuotationNotReady;
use App\Services\QuotationService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller implements HasMiddleware
{
    public function __construct(protected QuotationService $quotations) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:quotations.view', only: ['index', 'show', 'print']),
            new Middleware('permission:quotations.create', only: ['create', 'store']),
            new Middleware('permission:quotations.edit', only: ['update', 'updateItems', 'send', 'convert', 'markAccepted', 'markDeclined']),
            new Middleware('permission:quotations.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $quotations = Quotation::query()
            ->with(['enquiry:id,reference', 'creator:id,name'])
            ->withCount('items')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_email', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like));
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->input('flag') === 'open', fn (Builder $q) => $q->open())
            ->when($request->input('flag') === 'lapsed', fn (Builder $q) => $q->lapsed())
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quotations.index', [
            'quotations' => $quotations,
            'statuses' => Quotation::STATUSES,
            'summary' => [
                'draft' => Quotation::where('status', 'draft')->count(),
                'sent' => Quotation::where('status', 'sent')->count(),
                'accepted' => Quotation::where('status', 'accepted')->whereNull('order_id')->count(),
                'lapsed' => Quotation::lapsed()->count(),
            ],
        ]);
    }

    /**
     * Start one, usually from an enquiry so the customer's details and what
     * they asked for come across with it.
     */
    public function create(Request $request): View
    {
        $enquiry = $request->filled('enquiry')
            ? Enquiry::where('public_token', $request->string('enquiry'))->firstOrFail()
            : null;

        return view('admin.quotations.create', [
            'enquiry' => $enquiry,
            'states' => indian_states(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDetails($request);

        $enquiry = $request->filled('enquiry_token')
            ? Enquiry::where('public_token', $request->string('enquiry_token'))->first()
            : null;

        $quotation = $this->quotations->create(
            $this->shapeDetails($data),
            $enquiry,
            auth('admin')->user(),
        );

        // Seed the first line from the product the enquiry was about, so
        // there is something to price rather than an empty page.
        if ($enquiry?->product) {
            $this->quotations->setItems($quotation, [[
                'product_id' => $enquiry->product->id,
                'name' => $enquiry->product->name,
                'sku' => $enquiry->product->sku,
                'hsn_code' => $enquiry->product->hsn_code,
                'quantity' => max(1, (int) ($enquiry->quantity ?: 1)),
                'unit_price' => $enquiry->product->effective_price,
                'gst_rate' => $enquiry->product->gst_rate,
            ]]);
        }

        return redirect()->route('admin.quotations.show', $quotation)
            ->with('success', 'Quotation '.$quotation->number.' started. Add the lines and send it.');
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load(['items', 'enquiry', 'creator:id,name', 'order:id,order_number,public_token']);

        return view('admin.quotations.show', [
            'quotation' => $quotation,
            'taxSummary' => $this->quotations->taxSummary($quotation),
            'states' => indian_states(),
            // Only active products can be added; quoting a draft is how a
            // customer ends up ordering something that was never for sale.
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'price', 'sale_price', 'gst_rate', 'hsn_code']),
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        if (! $quotation->isEditable()) {
            return back()->with('error', 'This quotation can no longer be changed.');
        }

        $data = $this->validateDetails($request, forUpdate: true);

        $quotation->update($this->shapeDetails($data) + [
            'discount' => $data['discount'] ?? 0,
            'delivery_charge' => $data['delivery_charge'] ?? 0,
            'delivery_days' => $data['delivery_days'] ?? null,
            'valid_until' => $data['valid_until'] ?? $quotation->valid_until,
            'terms' => $data['terms'] ?? $quotation->terms,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->quotations->reprice($quotation->fresh('items'));

        return back()->with('success', 'Quotation updated.');
    }

    public function updateItems(Request $request, Quotation $quotation): RedirectResponse
    {
        if (! $quotation->isEditable()) {
            return back()->with('error', 'This quotation can no longer be changed.');
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.sku' => ['nullable', 'string', 'max:64'],
            'items.*.hsn_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.gst_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'items.required' => 'A quotation needs at least one line.',
            'items.*.name.required' => 'Every line needs a description.',
        ]);

        $this->quotations->setItems($quotation, $data['items']);

        return back()->with('success', 'Lines saved.');
    }

    public function send(Quotation $quotation): RedirectResponse
    {
        try {
            $quotation = $this->quotations->send($quotation);
        } catch (QuotationNotReady $e) {
            return back()->with('error', $e->getMessage());
        }

        $sent = $this->email($quotation);

        return back()->with($sent ? 'success' : 'error', $sent
            ? 'Quotation '.$quotation->number.' sent to '.$quotation->customer_email.'.'
            : 'Quotation marked as sent, but the email could not go out — check the mail settings.');
    }

    /**
     * Staff recording an answer that came by phone.
     */
    public function markAccepted(Quotation $quotation): RedirectResponse
    {
        if (! $quotation->isAnswerable()) {
            return back()->with('error', 'Only a live, sent quotation can be accepted.');
        }

        $this->quotations->accept($quotation);

        return back()->with('success', 'Marked as accepted. It can now be turned into an order.');
    }

    public function markDeclined(Request $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validate([
            'decline_reason' => ['nullable', 'string', 'max:300'],
        ]);

        if (! $quotation->isAnswerable()) {
            return back()->with('error', 'Only a live, sent quotation can be declined.');
        }

        $this->quotations->decline($quotation, $data['decline_reason'] ?? null);

        return back()->with('success', 'Marked as declined.');
    }

    public function convert(Quotation $quotation): RedirectResponse
    {
        try {
            $order = $this->quotations->convertToOrder($quotation, auth('admin')->user());
        } catch (QuotationNotReady $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Order '.$order->order_number.' raised from quotation '.$quotation->number.'.');
    }

    /**
     * The printable document, laid out for A4 like the invoice.
     */
    public function print(Quotation $quotation): Renderable
    {
        $quotation->load('items');

        return view('admin.quotations.print', [
            'quotation' => $quotation,
            'taxSummary' => $this->quotations->taxSummary($quotation),
            'backUrl' => route('admin.quotations.show', $quotation),
        ]);
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        if ($quotation->status !== 'draft') {
            return back()->with('error',
                'Only a draft can be deleted. A quotation the customer has seen is a record — decline it instead.');
        }

        $number = $quotation->number;
        $quotation->delete();

        return redirect()->route('admin.quotations.index')
            ->with('success', 'Draft '.$number.' deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateDetails(Request $request, bool $forUpdate = false): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'gst_number' => ['nullable', 'string', 'regex:/^[0-9A-Z]{15}$/'],

            'shipping.name' => ['nullable', 'string', 'max:120'],
            'shipping.phone' => ['nullable', 'string', 'max:20'],
            'shipping.line1' => ['nullable', 'string', 'max:180'],
            'shipping.line2' => ['nullable', 'string', 'max:180'],
            'shipping.city' => ['nullable', 'string', 'max:120'],
            // The quoted GST depends on this, so it comes from the list.
            'shipping.state' => ['nullable', 'string', Rule::in(array_keys(indian_states()))],
            'shipping.pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],

            'discount' => [$forUpdate ? 'nullable' : 'sometimes', 'numeric', 'min:0', 'max:99999999'],
            'delivery_charge' => [$forUpdate ? 'nullable' : 'sometimes', 'numeric', 'min:0', 'max:99999999'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'shipping.state.in' => 'Choose the delivery state from the list.',
            'gst_number.regex' => 'A GST number is 15 characters, for example 07AABCF1234M1Z5.',
            'valid_until.after_or_equal' => 'The validity date cannot be in the past.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function shapeDetails(array $data): array
    {
        $shipping = array_filter($data['shipping'] ?? [], fn ($value) => filled($value));

        return [
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'],
            'gst_number' => $data['gst_number'] ?? null,
            'shipping_address' => $shipping ?: null,
            'billing_address' => $shipping ?: null,
        ];
    }

    protected function email(Quotation $quotation): bool
    {
        try {
            Mail::to($quotation->customer_email)->send(new QuotationSent($quotation));

            return true;
        } catch (\Throwable $e) {
            Log::error('Quotation email failed', [
                'quotation' => $quotation->number,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
