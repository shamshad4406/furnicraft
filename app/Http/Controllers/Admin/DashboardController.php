<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoginHistory;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth('admin')->user();

        // Each tile is gated by the same permission as the module it summarises,
        // so a role never sees figures from a module it cannot open.
        $stats = [];

        // Orders first: on any working day this is what staff open the panel
        // for, and a figure further down the page is a figure nobody reads.
        if ($user->can('orders.view')) {
            $needsAttention = Order::whereIn('status', ['pending', 'confirmed'])->count();

            $stats[] = [
                'label' => 'Orders to action',
                'value' => $needsAttention,
                'sub' => Order::where('status', 'processing')->count().' being prepared · '
                    .Order::where('status', 'shipped')->count().' out',
                'icon' => 'bi-bag-check',
                'tone' => 'gold',
            ];

            // Cancelled and returned orders are not revenue, and neither is
            // money that has not arrived yet.
            $paidThisMonth = Order::whereNotIn('status', ['cancelled', 'returned'])
                ->where('placed_at', '>=', now()->startOfMonth())
                ->sum('amount_paid');

            $stats[] = [
                'label' => 'Received this month',
                'value' => money($paidThisMonth),
                'sub' => Order::whereNotIn('status', ['cancelled', 'returned'])
                    ->where('placed_at', '>=', now()->startOfMonth())->count().' order(s)',
                'icon' => 'bi-cash-stack',
                'tone' => 'teal',
                'raw' => true,
            ];

            $due = Order::where('payment_status', '!=', 'paid')
                ->whereNotIn('status', ['cancelled', 'returned'])
                ->sum('amount_due');

            if ($due > 0) {
                $stats[] = [
                    'label' => 'Still to collect',
                    'value' => money($due),
                    'sub' => 'Mostly cash on delivery',
                    'icon' => 'bi-hourglass-split',
                    'tone' => 'rose',
                    'raw' => true,
                ];
            }

            $openReturns = OrderReturn::whereNotIn('status', OrderReturn::FINAL)->count();

            if ($openReturns > 0) {
                $stats[] = [
                    'label' => 'Returns waiting',
                    'value' => $openReturns,
                    'sub' => 'Customers waiting on an answer',
                    'icon' => 'bi-arrow-return-left',
                    'tone' => 'rose',
                ];
            }
        }

        if ($user->can('users.view')) {
            $stats[] = [
                'label' => 'Staff Users',
                'value' => User::query()->count(),
                'sub' => User::query()->active()->count().' active',
                'icon' => 'bi-people',
                'tone' => 'navy',
            ];
        }

        if ($user->can('roles.view')) {
            $stats[] = [
                'label' => 'Roles',
                'value' => Role::query()->where('guard_name', 'admin')->count(),
                'sub' => 'Permission sets configured',
                'icon' => 'bi-shield-lock',
                'tone' => 'gold',
            ];
        }

        if ($user->can('products.view')) {
            $stats[] = [
                'label' => 'Products',
                'value' => Product::query()->count(),
                'sub' => Product::query()->active()->count().' active · '
                    .Product::query()->where('status', 'draft')->count().' draft',
                'icon' => 'bi-box-seam',
                'tone' => 'teal',
            ];
        }

        if ($user->can('categories.view')) {
            $stats[] = [
                'label' => 'Categories',
                'value' => Category::query()->count(),
                'sub' => Category::query()->roots()->count().' top level',
                'icon' => 'bi-diagram-3',
                'tone' => 'gold',
            ];
        }

        if ($user->can('customers.view')) {
            $stats[] = [
                'label' => 'Customers',
                'value' => Customer::query()->count(),
                'sub' => 'Storefront accounts',
                'icon' => 'bi-person-heart',
                'tone' => 'teal',
            ];
        }

        if ($user->can('activity.view')) {
            $stats[] = [
                'label' => 'Logins (7 days)',
                'value' => LoginHistory::query()->where('successful', true)->where('created_at', '>=', now()->subDays(7))->count(),
                'sub' => LoginHistory::query()->where('successful', false)->where('created_at', '>=', now()->subDays(7))->count().' failed',
                'icon' => 'bi-box-arrow-in-right',
                'tone' => 'rose',
            ];
        }

        $canSeeAudit = $user->can('activity.view');

        $activities = $canSeeAudit
            ? Activity::query()->with('causer')->latest()->limit(8)->get()
            : collect();

        $logins = $canSeeAudit
            ? LoginHistory::query()->with('authenticatable')->latest()->limit(8)->get()
            : collect();

        // Phase map, so the owner can see what is built and what is next.
        // config/phases.php is the single source; moving on is one edit there.
        $current = (int) config('phases.current');

        $phases = collect(config('phases.list'))
            ->map(fn (array $phase, int $no) => $phase + [
                'no' => $no,
                'status' => match (true) {
                    $no < $current => 'done',
                    $no === $current => 'current',
                    default => 'planned',
                },
            ])
            ->values()
            ->all();

        return view('admin.dashboard', compact('stats', 'activities', 'logins', 'phases', 'canSeeAudit'));
    }
}
