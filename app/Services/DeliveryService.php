<?php

namespace App\Services;

use App\Models\Pincode;

/**
 * Pincode serviceability and delivery estimate.
 *
 * Phase 3 answers "do you deliver here, and in how many days". The charge
 * fields on the zone are stored now and applied at checkout in Phase 4.
 */
class DeliveryService
{
    /**
     * @return array{found: bool, serviceable: bool, pincode: string, city: ?string, state: ?string, days: ?int, eta: ?string, cod: bool, message: string, note: ?string}
     */
    public function check(string $pincode): array
    {
        $pincode = trim($pincode);

        $base = [
            'found' => false,
            'serviceable' => false,
            'pincode' => $pincode,
            'city' => null,
            'state' => null,
            'days' => null,
            'eta' => null,
            'cod' => false,
            'note' => null,
        ];

        if (! preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
            return $base + ['message' => 'Please enter a valid 6-digit pincode.'];
        }

        $record = Pincode::with('zone')->where('pincode', $pincode)->first();

        if (! $record) {
            return $base + [
                'message' => 'We have not mapped this pincode yet. Send us an enquiry and we will confirm by phone.',
            ];
        }

        if (! $record->is_serviceable) {
            // A staff-written note replaces the generic line rather than
            // repeating it underneath.
            return array_merge($base, [
                'found' => true,
                'city' => $record->city,
                'state' => $record->state,
                'note' => null,
                'message' => $record->note ?: 'Sorry, we do not deliver to '.$record->city.' yet.',
            ]);
        }

        $days = $record->effective_delivery_days;

        return [
            'found' => true,
            'serviceable' => true,
            'pincode' => $pincode,
            'city' => $record->city,
            'state' => $record->state,
            'days' => $days,
            'eta' => now()->addDays($days)->format('D, d M Y'),
            'cod' => $record->effective_cod,
            'note' => $record->note,
            'message' => 'Delivery to '.$record->city.' in '.$days.' '.str('day')->plural($days).'.',
        ];
    }
}
