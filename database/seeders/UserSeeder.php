<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin/Expert user
        User::create([
            'name' => 'chef_martin',
            'email' => 'martin@example.com',
            'password' => Hash::make('password'),
            'is_premium' => true,
            'is_expert' => true,
            'premium_expire' => now()->addYear(),
            'first_name' => 'Martin',
            'last_name' => 'Dupont',
            'biography' => 'Chef cuisinier avec 15 ans d\'expérience dans la gastronomie française.',
            'avatar_url' => 'https://i.pravatar.cc/150?u=martin',
        ]);

        // Expert user
        User::create([
            'name' => 'chef_sophie',
            'email' => 'sophie@example.com',
            'password' => Hash::make('password'),
            'is_premium' => true,
            'is_expert' => true,
            'premium_expire' => now()->addYear(),
            'first_name' => 'Sophie',
            'last_name' => 'Bernard',
            'biography' => 'Passionnée de pâtisserie et cuisines du monde.',
            'avatar_url' => 'https://i.pravatar.cc/150?u=sophie',
        ]);

        // Premium user
        User::create([
            'name' => 'jean_cook',
            'email' => 'jean@example.com',
            'password' => Hash::make('password'),
            'is_premium' => true,
            'is_expert' => false,
            'premium_expire' => now()->addMonths(6),
            'first_name' => 'Jean',
            'last_name' => 'Lefevre',
            'biography' => 'Amateur de cuisine qui aime partager ses découvertes.',
            'avatar_url' => 'https://i.pravatar.cc/150?u=jean',
        ]);

        // Regular users
        User::create([
            'name' => 'marie_foodie',
            'email' => 'marie@example.com',
            'password' => Hash::make('password'),
            'is_premium' => false,
            'is_expert' => false,
            'premium_expire' => null,
            'first_name' => 'Marie',
            'last_name' => 'Petit',
            'biography' => 'J\'adore cuisiner pour ma famille!',
            'avatar_url' => 'https://i.pravatar.cc/150?u=marie',
        ]);

        User::create([
            'name' => 'pierre_gourmet',
            'email' => 'pierre@example.com',
            'password' => Hash::make('password'),
            'is_premium' => false,
            'is_expert' => false,
            'premium_expire' => null,
            'first_name' => 'Pierre',
            'last_name' => 'Moreau',
            'biography' => 'Débutant en cuisine, toujours prêt à apprendre.',
            'avatar_url' => 'https://i.pravatar.cc/150?u=pierre',
        ]);

        User::create([
            'name' => 'claire_kitchen',
            'email' => 'claire@example.com',
            'password' => Hash::make('password'),
            'is_premium' => false,
            'is_expert' => false,
            'premium_expire' => null,
            'first_name' => 'Claire',
            'last_name' => 'Dubois',
            'biography' => 'Fan de recettes healthy et végétariennes.',
            'avatar_url' => 'https://i.pravatar.cc/150?u=claire',
        ]);
    }
}
