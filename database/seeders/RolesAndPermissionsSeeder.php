<?php

namespace Database\Seeders;

use App\Domain\Authorization\PermissionRegistrar;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Permissions from catalog
        $permRows = [];
        foreach ($registrar::catalog() as $name => $group) {
            $permRows[] = ['name' => $name, 'group' => $group, 'label' => ucwords(str_replace('.', ' ', str_replace('_', ' ', $name)))];
        }
        Permission::upsert($permRows, ['name'], ['group', 'label']);

        $permIds = Permission::pluck('id', 'name');

        // Roles
        $roleRows = [];
        foreach ($registrar->roles() as $role) {
            $roleRows[] = $role;
        }
        Role::upsert($roleRows, ['name'], ['label', 'group', 'is_staff', 'requires_two_factor']);

        $roleIds = Role::pluck('id', 'name');

        // Role permission assignments (idempotent: rebuild)
        $assignRows = [];
        $seen = [];
        foreach ($registrar->roleAssignments() as $roleName => $permissions) {
            if (! isset($roleIds[$roleName])) {
                continue;
            }
            foreach (array_unique($permissions) as $perm) {
                if (! isset($permIds[$perm])) {
                    continue;
                }
                $pairKey = $roleIds[$roleName].':'.$permIds[$perm];
                if (isset($seen[$pairKey])) {
                    continue;
                }
                $seen[$pairKey] = true;
                $assignRows[] = ['role_id' => $roleIds[$roleName], 'permission_id' => $permIds[$perm]];
            }
        }

        DB::table('role_permission')->delete();
        foreach (array_chunk($assignRows, 500) as $chunk) {
            DB::table('role_permission')->insert($chunk);
        }

        $registrar->clearCache();
    }
}
