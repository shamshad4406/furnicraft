<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:suppliers.view', only: ['index', 'show']),
            new Middleware('permission:suppliers.create', only: ['create', 'store']),
            new Middleware('permission:suppliers.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:suppliers.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('contact_person', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('gst_number', 'like', $like));
            })
            ->when($request->input('status') === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('admin.suppliers.form', [
            'supplier' => new Supplier(['is_active' => true]),
            'states' => indian_states(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $supplier = Supplier::create($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.suppliers.show', $supplier)
            ->with('success', 'Supplier "'.$supplier->name.'" added.');
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['purchaseOrders' => fn ($q) => $q->with('items')->limit(20)]);

        return view('admin.suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.form', [
            'supplier' => $supplier,
            'states' => indian_states(),
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.suppliers.show', $supplier)
            ->with('success', 'Supplier updated.');
    }

    public function toggle(Supplier $supplier): RedirectResponse
    {
        $supplier->is_active = ! $supplier->is_active;
        $supplier->save();

        return back()->with('success',
            $supplier->name.' is now '.($supplier->is_active ? 'active' : 'inactive').'.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        // A supplier with purchase history is part of the record of where
        // stock came from.
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error',
                'This supplier has purchase orders against them, so they cannot be deleted. Deactivate them instead.');
        }

        $name = $supplier->name;
        $supplier->delete();

        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Supplier "'.$name.'" deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('suppliers', 'code')->ignore($supplier?->id)->whereNull('deleted_at'),
            ],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'alternate_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'address' => ['nullable', 'string', 'max:300'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', Rule::in(array_keys(indian_states()))],
            'pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'gst_number' => ['nullable', 'string', 'regex:/^[0-9A-Z]{15}$/'],
            'pan_number' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'code.regex' => 'A supplier code may only contain letters, numbers, hyphens and underscores.',
            'gst_number.regex' => 'A GST number is 15 characters, for example 07AABCF1234M1Z5.',
            'pan_number.regex' => 'A PAN is 10 characters, for example AABCF1234M.',
            'state.in' => 'Choose the state from the list.',
        ]);

        // A code nobody typed is generated, so purchase orders always have
        // something short to show.
        $data['code'] = Str::upper($data['code'] ?? '') ?: $this->codeFor($data['name']);

        return $data;
    }

    protected function codeFor(string $name): string
    {
        $base = Str::upper(Str::substr(Str::slug($name, ''), 0, 6)) ?: 'SUP';
        $code = $base;
        $suffix = 1;

        while (Supplier::withTrashed()->where('code', $code)->exists()) {
            $code = $base.(++$suffix);
        }

        return $code;
    }
}
