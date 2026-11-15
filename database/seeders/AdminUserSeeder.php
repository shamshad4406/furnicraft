<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
        $password = $this->password();
        $created = [];

        foreach ($this->staff as $row) {
            $user = User::withTrashed()->firstOrNew(['email' => $row['email']]);

            $user->fill([
                'name' => $row['name'],
                'designation' => $row['designation'],
                'is_active' => true,
            ]);

            if (! $user->exists) {
                $user->password = $password;
                $user->email_verified_at = now();
                $created[] = $row['email'];
            }

            $user->deleted_at = null;
            $user->save();

            $user->syncRoles([$row['role']]);
        }

        $this->report($password, $created);
    }

    /**
     * A shared well-known password is fine for local work, but outside local
     * it must not be guessable. SEED_ADMIN_PASSWORD overrides either way.
     */
    protected function password(): string
    {
        $configured = env('SEED_ADMIN_PASSWORD');

        if (filled($configured)) {
            return $configured;
        }

        return app()->environment('local', 'testing')
            ? 'Furni@2026'
            : Str::password(20);
    }

    /**
     * @param  string[]  $created
     */
    protected function report(string $password, array $created): void
    {
        if ($created === []) {
            $this->command?->info('Staff accounts already present — passwords left untouched.');

            return;
        }

        $this->command?->info('Seeded '.count($created).' staff account(s).');

        if (app()->environment('local', 'testing') || filled(env('SEED_ADMIN_PASSWORD'))) {
            return;
        }

        // Printed once, never stored anywhere else.
        $this->command?->warn('Generated password for the seeded accounts: '.$password);
        $this->command?->warn('Write it down now — it is not shown again. Change it after the first sign-in.');
    }
}
