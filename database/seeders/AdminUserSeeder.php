<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Default staff accounts, one per role, for the Phase 1 demo.
     *
     * @var array<int, array<string, string>>
     */
    protected array $staff = [
        ['name' => 'FurniCraft Admin', 'email' => 'admin@furnicraft.in', 'role' => 'Admin', 'designation' => 'Administrator'],
        ['name' => 'Store Manager', 'email' => 'manager@furnicraft.in', 'role' => 'Manager', 'designation' => 'Store Manager'],
        ['name' => 'Sales Executive', 'email' => 'sales@furnicraft.in', 'role' => 'Sales', 'designation' => 'Sales Executive'],
        ['name' => 'Inventory Incharge', 'email' => 'inventory@furnicraft.in', 'role' => 'Inventory', 'designation' => 'Store Keeper'],
        ['name' => 'Delivery Staff', 'email' => 'delivery@furnicraft.in', 'role' => 'Delivery', 'designation' => 'Delivery Executive'],
    ];

    public function run(): void
    {
        foreach ($this->staff as $row) {
            $user = User::withTrashed()->firstOrNew(['email' => $row['email']]);

            $user->fill([
                'name' => $row['name'],
                'designation' => $row['designation'],
                'is_active' => true,
            ]);

            if (! $user->exists) {
                // Change this immediately after the first login on staging/live.
                $user->password = env('SEED_ADMIN_PASSWORD', 'Furni@2026');
                $user->email_verified_at = now();
            }

            $user->deleted_at = null;
            $user->save();

            $user->syncRoles([$row['role']]);
        }

        $this->command?->info('Seeded '.count($this->staff).' staff accounts (default password: Furni@2026).');
    }
}
