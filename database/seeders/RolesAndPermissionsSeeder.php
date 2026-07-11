<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles/permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'users.list',
            'courses.list',
            'recipes.list',
            'users.view',
            'courses.view',
            'recipes.view',
            'premium-recipes.view',
            'courses.create',
            'courses.update',
            'courses.delete',
            'recipes.create',
            'recipes.update',
            'recipes.delete',
            'comments.create',
            'comments.update',
            'comments.delete',
            'likes.update',
            'favorites.update',
            'premium-recipes.create',
            'premium-recipes.update',
            'premium-recipes.delete',
            'comments.create',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $admin->givePermissionTo(Permission::all());

        $regularUser = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'sanctum']);
        $regularUser->givePermissionTo([
            'users.view',
            'users.list',
            'courses.view',
            'courses.list',
            'recipes.view',
            'recipes.list',
        ]);

        $premiumUser = Role::firstOrCreate(['name' => 'premium_user', 'guard_name' => 'sanctum']);
        $premiumUser->givePermissionTo([
            'users.view',
            'users.list',
            'courses.view',
            'courses.list',
            'recipes.view',
            'recipes.list',
            'premium-recipes.view',
            'comments.create',
            'comments.update',
            'comments.delete',
            'likes.update',
            'favorites.update',
        ]);

        $chef = Role::firstOrCreate(['name' => 'chef', 'guard_name' => 'sanctum']);
        $chef->givePermissionTo([
            'users.view',
            'users.list',
            'courses.view',
            'courses.list',
            'recipes.view',
            'recipes.list',
            'premium-recipes.view',
            'courses.create',
            'courses.update',
            'courses.delete',
            'recipes.create',
            'recipes.update',
            'recipes.delete',
            'premium-recipes.create',
            'premium-recipes.update',
            'premium-recipes.delete',
            'comments.create',
            'comments.update',
            'comments.delete',
            'likes.update',
            'favorites.update',
        ]);

        // Assign some roles for testing.
        $allUsers = \App\Models\User::all();
        foreach ($allUsers as $user) {
            if ($user->email === 'admin@example.com') {
                $user->assignRole($admin);
            } elseif ($user->is_expert) {
                $user->assignRole($chef);
            } elseif ($user->is_premium) {
                $user->assignRole($premiumUser);
            } else {
                $user->assignRole($regularUser);
            }
        }
    }
}
