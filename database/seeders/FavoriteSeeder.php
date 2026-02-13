<?php

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $recipes = Recipe::all();

        foreach ($users as $user) {
            // Each user favorites 1-3 random recipes
            $numFavorites = rand(1, 3);
            $favoriteRecipes = $recipes->shuffle()->take($numFavorites);

            foreach ($favoriteRecipes as $recipe) {
                // Avoid duplicates
                Favorite::firstOrCreate([
                    'user_id' => $user->id,
                    'recipe_id' => $recipe->id,
                ]);
            }
        }
    }
}
