<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\Pincode;
use Illuminate\Database\Seeder;

/**
 * Starter delivery zones and a sample pincode list.
 *
 * The client replaces the pincode rows with their real serviceable list from
 * Admin → Delivery → Pincodes. Charges are stored now and applied at checkout
 * in Phase 4.
 */
class DeliverySeeder extends Seeder
{
    /** @var array<string, array{days: int, base: float, per_kg: float, free_above: float|null, cod: bool, description: string}> */
    protected array $zones = [
        'Local — Delhi NCR' => ['days' => 3, 'base' => 0, 'per_kg' => 0, 'free_above' => 0, 'cod' => true,
            'description' => 'Own delivery fleet, free installation'],
        'North India' => ['days' => 6, 'base' => 450, 'per_kg' => 12, 'free_above' => 25000, 'cod' => true,
            'description' => 'Punjab, Haryana, UP, Rajasthan, Uttarakhand'],
        'Rest of India' => ['days' => 10, 'base' => 900, 'per_kg' => 18, 'free_above' => 40000, 'cod' => false,
            'description' => 'Surface transport, prepaid only'],
    ];

    /** @var array<int, array{0: string, 1: string, 2: string, 3: string, 4?: int, 5?: string}> */
    protected array $pincodes = [
        // pincode, city, state, zone
        ['110001', 'New Delhi', 'Delhi', 'Local — Delhi NCR'],
        ['110019', 'New Delhi', 'Delhi', 'Local — Delhi NCR'],
        ['110092', 'New Delhi', 'Delhi', 'Local — Delhi NCR'],
        ['201301', 'Noida', 'Uttar Pradesh', 'Local — Delhi NCR'],
        ['201001', 'Ghaziabad', 'Uttar Pradesh', 'Local — Delhi NCR'],
        ['122001', 'Gurugram', 'Haryana', 'Local — Delhi NCR'],
        ['121001', 'Faridabad', 'Haryana', 'Local — Delhi NCR'],

        ['160017', 'Chandigarh', 'Chandigarh', 'North India'],
        ['141001', 'Ludhiana', 'Punjab', 'North India'],
        ['226001', 'Lucknow', 'Uttar Pradesh', 'North India'],
        ['208001', 'Kanpur', 'Uttar Pradesh', 'North India'],
        ['302001', 'Jaipur', 'Rajasthan', 'North India'],
        ['248001', 'Dehradun', 'Uttarakhand', 'North India'],

        ['400001', 'Mumbai', 'Maharashtra', 'Rest of India'],
        ['411001', 'Pune', 'Maharashtra', 'Rest of India'],
        ['560001', 'Bengaluru', 'Karnataka', 'Rest of India'],
        ['600001', 'Chennai', 'Tamil Nadu', 'Rest of India'],
        ['500001', 'Hyderabad', 'Telangana', 'Rest of India'],
        ['700001', 'Kolkata', 'West Bengal', 'Rest of India'],
        ['380001', 'Ahmedabad', 'Gujarat', 'Rest of India'],

        // A deliberately non-serviceable example so staff can see how it reads.
        ['682001', 'Kochi', 'Kerala', 'Rest of India', 0, 'not-serviceable'],
    ];

    public function run(): void
    {
        $zoneIds = [];

        foreach ($this->zones as $name => $config) {
            $zone = DeliveryZone::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $config['description'],
                    'delivery_days' => $config['days'],
                    'base_charge' => $config['base'],
                    'per_kg_charge' => $config['per_kg'],
                    'free_above' => $config['free_above'] ?: null,
                    'cod_available' => $config['cod'],
                    'is_active' => true,
                ]
            );

            $zoneIds[$name] = $zone->id;
        }

        $count = 0;

        foreach ($this->pincodes as $row) {
            [$pincode, $city, $state, $zoneName] = $row;
            $serviceable = ($row[5] ?? null) !== 'not-serviceable';

            Pincode::firstOrCreate(
                ['pincode' => $pincode],
                [
                    'city' => $city,
                    'state' => $state,
                    'delivery_zone_id' => $zoneIds[$zoneName],
                    'is_serviceable' => $serviceable,
                    'cod_available' => $serviceable,
                    'note' => $serviceable ? null : 'We do not deliver to this area yet.',
                ]
            );

            $count++;
        }

        $this->command?->info('Seeded '.count($zoneIds).' delivery zones and '.$count.' pincodes.');
    }
}
