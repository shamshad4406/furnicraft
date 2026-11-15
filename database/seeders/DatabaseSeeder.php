<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SettingSeeder::class,
            CatalogSeeder::class,
            CatalogDemoImageSeeder::class,
            DeliverySeeder::class,
            CmsPageSeeder::class,
            ShopDemoSeeder::class,
        ]);
    }
}
