<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customer accounts, from the shop's side.
 *
 * Read-mostly on purpose. Staff can see who somebody is and what they have
 * bought, and disable an account that is causing trouble — but they cannot
 * change somebody's email or password, because an account somebody else can
 * silently take over is not an account.
 */
class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index', 'show', 'export']),
            // Disabling an account is the only thing staff may change here.
            new Middleware('permission:customers.edit', only: ['toggle']),
        ];
    }

    public function index(Request $request): View
    {
        $customers = $this->filtered($request)
            ->withCount(['orders', 'addresses'])
            ->withSum(['orders as spent' => fn (Builder $q) => $q->whereNotIn('status', ['cancelled', 'returned'])], 'amount_paid')
            ->withMax('orders as last_order_at', 'placed_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'summary' => [
                'total' => Customer::count(),
                'active' => Customer::where('is_active', true)->count(),
                'withOrders' => Customer::has('orders')->count(),
                'newThisMonth' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
        ]);
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'addresses',
            'orders' => fn ($q) => $q->with('items:id,order_id,name,quantity')->limit(25),
        ]);

        return view('admin.customers.show', [
            'customer' => $customer,
            'stats' => [
                'orders' => $customer->orders()->count(),
                'spent' => (float) $customer->orders()
                    ->whereNotIn('status', ['cancelled', 'returned'])->sum('amount_paid'),
                'cancelled' => $customer->orders()->where('status', 'cancelled')->count(),
                'enquiries' => \App\Models\Enquiry::where('customer_id', $customer->id)->count(),
            ],
        ]);
    }

    public function toggle(Customer $customer): RedirectResponse
    {
        $customer->is_active = ! $customer->is_active;
        $customer->save();

        return back()->with('success', $customer->is_active
            ? $customer->name.' can sign in again.'
            : $customer->name.' has been disabled and will be signed out.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)
            ->withCount('orders')
            ->withSum(['orders as spent' => fn (Builder $q) => $q->whereNotIn('status', ['cancelled', 'returned'])], 'amount_paid');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['name', 'email', 'phone', 'orders', 'spent', 'active', 'joined', 'last_signed_in']);

            $query->chunk(200, function ($customers) use ($out) {
                foreach ($customers as $customer) {
                    fputcsv($out, [
                        $customer->name,
                        $customer->email,
                        $customer->phone,
                        $customer->orders_count,
                        $customer->spent ?? 0,
                        $customer->is_active ? 'yes' : 'no',
                        $customer->created_at->format('Y-m-d'),
                        $customer->last_login_at?->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($out);
        }, 'furnicraft-customers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<Customer>
     */
    protected function filtered(Request $request): Builder
    {
        return Customer::query()
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->when($request->input('status') === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($request->input('status') === 'disabled', fn (Builder $q) => $q->where('is_active', false))
            ->when($request->input('flag') === 'buyers', fn (Builder $q) => $q->has('orders'))
            ->when($request->input('flag') === 'never_ordered', fn (Builder $q) => $q->doesntHave('orders'))
            ->latest('id');
    }
}
