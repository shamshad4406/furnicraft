<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoginHistory;
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
        $phases = [
            ['no' => 1, 'name' => 'Project Setup & Foundation', 'weeks' => '1', 'status' => 'done'],
            ['no' => 2, 'name' => 'Catalog Management', 'weeks' => '2', 'status' => 'current'],
            ['no' => 3, 'name' => 'Customer Storefront', 'weeks' => '3', 'status' => 'planned'],
            ['no' => 4, 'name' => 'Cart, Checkout & Payments', 'weeks' => '3', 'status' => 'planned'],
            ['no' => 5, 'name' => 'Orders & Delivery', 'weeks' => '2', 'status' => 'planned'],
            ['no' => 6, 'name' => 'Enquiry & Quotation', 'weeks' => '1.5', 'status' => 'planned'],
            ['no' => 7, 'name' => 'Inventory & Reports', 'weeks' => '2', 'status' => 'planned'],
            ['no' => 8, 'name' => 'Marketing & SEO', 'weeks' => '1', 'status' => 'planned'],
            ['no' => 9, 'name' => 'Testing & Launch', 'weeks' => '1.5', 'status' => 'planned'],
        ];

        return view('admin.dashboard', compact('stats', 'activities', 'logins', 'phases', 'canSeeAudit'));
    }
}
