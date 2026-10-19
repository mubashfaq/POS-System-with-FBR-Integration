<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserBusiness;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // -------------------------------------------------------------
        // 1. Roles (super_admin is created manually in the DB by dev)
        // -------------------------------------------------------------
        foreach (['Admin', 'cashier', 'sales_associate'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // -------------------------------------------------------------
        // 2. Permissions (single source of truth: config/permissions.php)
        // -------------------------------------------------------------
        $permissionNames = config('permissions.list');

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // -------------------------------------------------------------
        // 3. Role → Permission assignments
        // -------------------------------------------------------------
        // Admin gets EVERYTHING
        Role::where('name', 'Admin')->first()->syncPermissions($permissionNames);

        // super_admin (dev-only, may not exist) also gets everything
        if ($superAdmin = Role::where('name', 'super_admin')->first()) {
            $superAdmin->syncPermissions($permissionNames);
        }

        // Other roles from config
        foreach (config('permissions.roles') as $roleName => $perms) {
            Role::where('name', $roleName)->first()->syncPermissions($perms);
        }

        // -------------------------------------------------------------
        // 4. Demo users (User records)
        // -------------------------------------------------------------
        $cashierUser = User::firstOrCreate(
            ['email' => 'cashier@gmail.com'],
            [
                'name' => 'Mr Cashier',
                'password' => bcrypt('12345678'),
                'username' => 'cashier',
            ]
        );

        $cashierUser->syncRoles(['cashier']);

        $salesUser = User::firstOrCreate(
            ['email' => 'sales@gmail.com'],
            [
                'name' => 'Mr Sales',
                'password' => bcrypt('12345678'),
                'username' => 'sales',
            ]
        );
        
        $salesUser->syncRoles(['sales_associate']);

        // -------------------------------------------------------------
        // 7. Flush cache again so the new pivot rows are visible
        // -------------------------------------------------------------
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}