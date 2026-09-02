<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LocationSeeder::class,
            CatalogSeeder::class,
            InitialAdminSeeder::class,
        ]);

        // Demo dataset is strictly opt-in and NEVER in production
        // (see config/demo.php + jasapedia:seed-demo command).
        if (config('demo.enabled') && app()->environment(['local', 'demo', 'testing'])) {
            $this->call(Demo\DemoDataSeeder::class);
        }
    }
}
