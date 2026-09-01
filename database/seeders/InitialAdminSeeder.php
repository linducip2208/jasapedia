<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@jasapedia.test'],
            [
                'name' => 'Jasapedia Admin',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $role = Role::where('name', 'SuperAdmin')->first();
        if ($role && ! $user->hasRole('SuperAdmin')) {
            $user->roles()->attach($role->id);
        }

        $this->command?->info('SuperAdmin: admin@jasapedia.test / password (change in production)');
    }
}
