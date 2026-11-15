<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:coupons.view', only: ['index', 'show']),
            new Middleware('permission:coupons.create', only: ['create', 'store']),
            new Middleware('permission:coupons.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:coupons.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $coupons = Coupon::query()
            ->withCount('usages')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($request->input('status') === 'live', fn ($q) => $q->live())
            ->when($request->input('status') === 'expired', fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<', now()))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        return view('admin.coupons.form', [
            'coupon' => new Coupon(['type' => 'percentage', 'is_active' => true, 'min_order_value' => 0]),
            'types' => Coupon::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coupon = Coupon::create($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon "'.$coupon->code.'" created.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', [
            'coupon' => $coupon,
            'types' => Coupon::TYPES,
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon "'.$coupon->code.'" updated.');
    }

    public function show(Coupon $coupon): View
    {
        return view('admin.coupons.show', [
            'coupon' => $coupon,
            'usages' => $coupon->usages()->with(['order:id,order_number,grand_total', 'coupon'])
                ->latest('id')->paginate(20),
        ]);
    }

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->is_active = ! $coupon->is_active;
        $coupon->save();

        return back()->with('success', $coupon->code.' is now '.($coupon->is_active ? 'active' : 'inactive').'.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->usages()->exists()) {
            return back()->with('error',
                'This coupon has been used on real orders, so it cannot be deleted. Deactivate it instead.');
        }

        $code = $coupon->code;
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon "'.$code.'" deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Coupon::TYPES))],
            // A percentage cannot exceed 100; a fixed amount has no such cap.
            'value' => [
                'required_unless:type,free_delivery', 'nullable', 'numeric', 'min:0',
                Rule::when(
                    $request->input('type') === 'percentage',
                    ['max:100'],
                    ['max:9999999'],
                ),
            ],
            'max_discount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'min_order_value' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
        ], [
            'code.regex' => 'A coupon code may only contain letters, numbers, hyphens and underscores.',
            'expires_at.after' => 'The expiry date must be after the start date.',
            'value.max' => $request->input('type') === 'percentage'
                ? 'A percentage discount cannot be more than 100.'
                : 'That discount amount is too large.',
        ]);

        // Codes are matched case-insensitively; store them in one shape.
        $data['code'] = mb_strtoupper($data['code']);

        if ($data['type'] === 'free_delivery') {
            $data['value'] = 0;
            $data['max_discount'] = null;
        }

        return $data;
    }
}
