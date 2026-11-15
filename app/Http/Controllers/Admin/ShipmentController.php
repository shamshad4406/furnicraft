<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentNotPossible;
use App\Services\ShipmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentController extends Controller implements HasMiddleware
{
    public function __construct(protected ShipmentService $shipments) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:shipments.view', only: ['index']),
            new Middleware('permission:shipments.create', only: ['store']),
            new Middleware('permission:shipments.edit', only: ['update', 'updateStatus']),
            new Middleware('permission:shipments.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $shipments = Shipment::query()
            ->with(['order:id,order_number,customer_name,shipping_pincode,public_token', 'assignee:id,name'])
            ->withCount('items')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('consignment_number', 'like', $like)
                    ->orWhere('tracking_number', 'like', $like)
                    ->orWhere('courier', 'like', $like)
                    ->orWhereHas('order', fn (Builder $o) => $o
                        ->where('order_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like))
                );
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->input('flag') === 'open', fn (Builder $q) => $q->whereNotIn('status', Shipment::CLOSED))
            ->when($request->filled('assigned_to'), fn (Builder $q) => $q->where('assigned_to', $request->input('assigned_to')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.shipments.index', [
            'shipments' => $shipments,
            'statuses' => Shipment::STATUSES,
            'staff' => $this->deliveryStaff(),
            'openCount' => Shipment::whereNotIn('status', Shipment::CLOSED)->count(),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'courier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'packages' => ['required', 'integer', 'min:1', 'max:999'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'expected_delivery_on' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(array_keys(Shipment::STATUSES))],
            'notify' => ['nullable', 'boolean'],
        ], [
            'expected_delivery_on.after_or_equal' => 'The expected delivery date cannot be in the past.',
        ]);

        try {
            $shipment = $this->shipments->create($order, $data['quantities'], $data);
        } catch (ShipmentNotPossible $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->shipments->syncOrderStatus($order->fresh(), auth('admin')->user());

        $message = 'Consignment '.$shipment->consignment_number.' created.';

        if ($request->boolean('notify') && $shipment->status !== 'pending') {
            $message .= $this->notify($order->fresh(), 'shipped')
                ? ' The customer has been emailed.'
                : ' The customer could not be emailed — check the mail settings.';
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validate([
            'courier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'packages' => ['required', 'integer', 'min:1', 'max:999'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'expected_delivery_on' => ['nullable', 'date'],
            'received_by' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $shipment->update($data);

        return back()->with('success', 'Consignment '.$shipment->consignment_number.' updated.');
    }

    public function updateStatus(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Shipment::STATUSES))],
            'received_by' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ]);

        if ($data['status'] === 'delivered' && filled($data['received_by'] ?? null)) {
            $shipment->received_by = $data['received_by'];
        }

        $this->shipments->updateStatus(
            $shipment,
            $data['status'],
            auth('admin')->user(),
            $data['notes'] ?? null,
        );

        $message = 'Consignment is now '.mb_strtolower(Shipment::STATUSES[$data['status']]).'.';

        if ($request->boolean('notify')) {
            $order = $shipment->order->fresh();
            $status = $data['status'] === 'delivered' ? 'delivered' : 'shipped';

            $message .= $this->notify($order, $status)
                ? ' The customer has been emailed.'
                : ' The customer could not be emailed — check the mail settings.';
        }

        return back()->with('success', $message);
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        if ($shipment->status === 'delivered') {
            return back()->with('error',
                'A delivered consignment is a record of what happened and cannot be removed. Mark it returned instead.');
        }

        $number = $shipment->consignment_number;
        $shipment->delete();

        return back()->with('success', 'Consignment '.$number.' removed. Its items are waiting to be sent again.');
    }

    /**
     * Staff who can be handed a delivery — the Delivery role, plus anyone
     * with shipment rights, so a small shop with one manager still works.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function deliveryStaff()
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'Delivery'))
                ->orWhereHas('roles', fn ($r) => $r->where('name', 'Admin')))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function notify(Order $order, string $status): bool
    {
        try {
            Mail::to($order->customer_email)->send(new OrderStatusChanged($order, $status));

            return true;
        } catch (\Throwable $e) {
            Log::error('Shipment email failed', [
                'order' => $order->order_number,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
