<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeliveryZoneController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:shipments.view', only: ['index']),
            new Middleware('permission:shipments.create', only: ['store']),
            new Middleware('permission:shipments.edit', only: ['update']),
            new Middleware('permission:shipments.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        return view('admin.delivery.zones', [
            'zones' => DeliveryZone::withCount('pincodes')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $zone = DeliveryZone::create($this->validated($request) + [
            'cod_available' => $request->boolean('cod_available'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Zone "'.$zone->name.'" created.');
    }

    public function update(Request $request, DeliveryZone $zone): RedirectResponse
    {
        $zone->update($this->validated($request, $zone) + [
            'cod_available' => $request->boolean('cod_available'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Zone "'.$zone->name.'" updated.');
    }

    public function destroy(DeliveryZone $zone): RedirectResponse
    {
        if ($zone->pincodes()->exists()) {
            return back()->with('error', 'Move the pincodes in "'.$zone->name.'" to another zone first.');
        }

        $name = $zone->name;
        $zone->delete();

        return back()->with('success', 'Zone "'.$name.'" deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?DeliveryZone $zone = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('delivery_zones', 'name')->ignore($zone?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'delivery_days' => ['required', 'integer', 'between:1,60'],
            'base_charge' => ['required', 'numeric', 'min:0', 'max:999999'],
            'per_kg_charge' => ['required', 'numeric', 'min:0', 'max:9999'],
            'free_above' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);
    }
}
