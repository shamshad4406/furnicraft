<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Pincode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pincode serviceability. Permissions ride on the shipments module, which is
 * where delivery lives from Phase 5 onwards.
 */
class PincodeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:shipments.view', only: ['index', 'export']),
            new Middleware('permission:shipments.create', only: ['store', 'import']),
            new Middleware('permission:shipments.edit', only: ['update', 'toggle']),
            new Middleware('permission:shipments.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $pincodes = Pincode::query()
            ->with('zone')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('pincode', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('state', 'like', $term));
            })
            ->when($request->filled('zone'), fn ($q) => $q->where('delivery_zone_id', $request->input('zone')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_serviceable', $request->input('status') === 'yes'))
            ->orderBy('pincode')
            ->paginate(20)
            ->withQueryString();

        return view('admin.delivery.pincodes', [
            'pincodes' => $pincodes,
            'zones' => DeliveryZone::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Pincode::create($data + [
            'is_serviceable' => $request->boolean('is_serviceable'),
            'cod_available' => $request->boolean('cod_available'),
        ]);

        return back()->with('success', 'Pincode '.$data['pincode'].' added.');
    }

    public function update(Request $request, Pincode $pincode): RedirectResponse
    {
        $data = $this->validated($request, $pincode);

        $pincode->update($data + [
            'is_serviceable' => $request->boolean('is_serviceable'),
            'cod_available' => $request->boolean('cod_available'),
        ]);

        return back()->with('success', 'Pincode '.$pincode->pincode.' updated.');
    }

    public function toggle(Pincode $pincode): RedirectResponse
    {
        $pincode->is_serviceable = ! $pincode->is_serviceable;
        $pincode->save();

        return back()->with('success', $pincode->pincode.' is now '
            .($pincode->is_serviceable ? 'serviceable' : 'not serviceable').'.');
    }

    public function destroy(Pincode $pincode): RedirectResponse
    {
        $code = $pincode->pincode;
        $pincode->delete();

        return back()->with('success', 'Pincode '.$code.' removed.');
    }

    /**
     * Bulk add from a pasted list: "pincode,city,state" per line.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'rows' => ['required', 'string', 'max:100000'],
            'delivery_zone_id' => ['required', Rule::exists('delivery_zones', 'id')],
        ]);

        $added = 0;
        $skipped = 0;
        $errors = [];

        foreach (preg_split('/\r\n|\r|\n/', $request->input('rows')) as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));
            [$code, $city, $state] = array_pad($parts, 3, null);

            if (! preg_match('/^[1-9][0-9]{5}$/', (string) $code) || ! $city || ! $state) {
                $errors[] = 'Line '.($index + 1).': expected "pincode, city, state".';
                continue;
            }

            if (Pincode::where('pincode', $code)->exists()) {
                $skipped++;
                continue;
            }

            Pincode::create([
                'pincode' => $code,
                'city' => $city,
                'state' => $state,
                'delivery_zone_id' => $request->input('delivery_zone_id'),
                'is_serviceable' => true,
                'cod_available' => true,
            ]);

            $added++;
        }

        $message = $added.' pincode(s) added, '.$skipped.' already existed.';

        return back()
            ->with($errors ? 'info' : 'success', $message)
            ->with('pincode_errors', array_slice($errors, 0, 20));
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['pincode', 'city', 'state', 'zone', 'delivery_days', 'serviceable', 'cod']);

            Pincode::with('zone')->orderBy('pincode')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->pincode, $row->city, $row->state,
                        $row->zone?->name,
                        $row->effective_delivery_days,
                        $row->is_serviceable ? 'yes' : 'no',
                        $row->effective_cod ? 'yes' : 'no',
                    ]);
                }
            });

            fclose($out);
        }, 'furnicraft-pincodes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Pincode $pincode = null): array
    {
        return $request->validate([
            'pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/', Rule::unique('pincodes', 'pincode')->ignore($pincode?->id)],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'delivery_zone_id' => ['nullable', Rule::exists('delivery_zones', 'id')],
            'delivery_days' => ['nullable', 'integer', 'between:1,60'],
            'note' => ['nullable', 'string', 'max:160'],
        ], [
            'pincode.regex' => 'Enter a valid 6-digit Indian pincode.',
            'pincode.unique' => 'This pincode is already in the list.',
        ]);
    }
}
