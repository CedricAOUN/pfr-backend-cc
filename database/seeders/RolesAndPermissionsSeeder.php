<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.list',
            'courses.list',
            'recipes.list',
            'users.view',
            'courses.view',
            'recipes.view',
            'premium-recipes.view',
            'courses.create',
            'recipes.create',
            'comments.create',
            'likes.update',
            'favorites.update',
            'premium-recipes.create',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $regularUser = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'sanctum']);
        $premiumUser = Role::firstOrCreate(['name' => 'premium_user', 'guard_name' => 'sanctum']);
        $chef = Role::firstOrCreate(['name' => 'chef', 'guard_name' => 'sanctum']);

        $regularPermissions = [
            'users.view',
            'users.list',
            'courses.view',
            'courses.list',
            'recipes.view',
            'recipes.list',
            'likes.update',
            'favorites.update',
        ];

        $premiumPermissions = [
            ...$regularPermissions,
            'premium-recipes.view',
            'recipes.create',
            'comments.create',
        ];

        $chefPermissions = [
            ...$premiumPermissions,
            'courses.create',
            'premium-recipes.create',
        ];

        $allPermissions = Permission::query()
            ->where('guard_name', 'sanctum')
            ->whereIn('name', $permissions)
            ->get();

        $admin->syncPermissions($allPermissions);
        $regularUser->syncPermissions($regularPermissions);
        $premiumUser->syncPermissions($premiumPermissions);
        $chef->syncPermissions($chefPermissions);

        $demoRoles = [
            'admin@example.com' => 'admin',
            'martin@example.com' => 'chef',
            'sophie@example.com' => 'chef',
            'jean@example.com' => 'premium_user',
            'marie@example.com' => 'regular_user',
            'pierre@example.com' => 'regular_user',
            'claire@example.com' => 'regular_user',
        ];

        foreach ($demoRoles as $email => $role) {
            User::where('email', $email)->first()?->syncRoles([$role]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
