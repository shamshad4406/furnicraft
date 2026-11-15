<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Module => actions. Modules for later phases are seeded now so the
     * role screen and the sidebar are complete from Phase 1 onwards.
     *
     * @var array<string, string[]>
     */
    public const MODULES = [
        'dashboard' => ['view'],
        'users' => ['view', 'create', 'edit', 'delete'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['view', 'edit'],
        'activity' => ['view'],
        'categories' => ['view', 'create', 'edit', 'delete'],
        'brands' => ['view', 'create', 'edit', 'delete'],
        'products' => ['view', 'create', 'edit', 'delete'],
        'cms' => ['view', 'create', 'edit', 'delete'],
        'customers' => ['view', 'create', 'edit', 'delete'],
        'coupons' => ['view', 'create', 'edit', 'delete'],
        'orders' => ['view', 'create', 'edit', 'delete'],
        'shipments' => ['view', 'create', 'edit', 'delete'],
        'enquiries' => ['view', 'create', 'edit', 'delete'],
        'quotations' => ['view', 'create', 'edit', 'delete'],
        'inventory' => ['view', 'create', 'edit', 'delete'],
        'suppliers' => ['view', 'create', 'edit', 'delete'],
        'purchases' => ['view', 'create', 'edit', 'delete'],
        'reports' => ['view'],
        'banners' => ['view', 'create', 'edit', 'delete'],
        'reviews' => ['view', 'edit', 'delete'],
        // Its own module, not a corner of 'banners': the list is personal
        // data, and somebody who may change a homepage slide has no
        // business exporting every subscriber's email address.
        'newsletter' => ['view', 'edit'],
    ];

    /**
     * Role => modules the role may use ('*' means every permission).
     *
     * @var array<string, string[]>
     */
    public const ROLES = [
        'Admin' => ['*'],
        'Manager' => [
            'dashboard', 'categories', 'brands', 'products', 'cms', 'customers',
            'coupons', 'orders', 'shipments', 'enquiries', 'quotations',
            'inventory', 'suppliers', 'purchases', 'reports', 'banners', 'reviews', 'newsletter',
            'activity',
        ],
        'Sales' => ['dashboard', 'enquiries', 'quotations', 'customers', 'orders', 'products', 'reports'],
        'Inventory' => ['dashboard', 'products', 'categories', 'brands', 'inventory', 'suppliers', 'purchases', 'reports'],
        'Delivery' => ['dashboard', 'shipments', 'orders'],
    ];

    /**
     * Roles that only get read access on the listed modules.
     *
     * @var array<string, string[]>
     */
    public const READ_ONLY = [
        'Sales' => ['products', 'orders'],
        'Inventory' => ['categories', 'brands'],
        'Delivery' => ['orders'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];

        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                Permission::findOrCreate($name, 'admin');
                $all[] = $name;
            }
        }

        foreach (self::ROLES as $roleName => $modules) {
            $role = Role::findOrCreate($roleName, 'admin');

            if ($modules === ['*']) {
                $role->syncPermissions($all);
                continue;
            }

            $granted = [];

            foreach ($modules as $module) {
                $actions = self::MODULES[$module] ?? [];

                if (in_array($module, self::READ_ONLY[$roleName] ?? [], true)) {
                    $actions = array_intersect($actions, ['view']);
                }

                foreach ($actions as $action) {
                    $granted[] = "{$module}.{$action}";
                }
            }

            $role->syncPermissions($granted);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Seeded '.count($all).' permissions and '.count(self::ROLES).' roles.');
    }
}
