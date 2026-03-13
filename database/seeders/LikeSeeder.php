<?php

namespace Database\Seeders;

use App\Models\Like;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recipes = Recipe::all();

        foreach ($recipes as $recipe) {
            Like::create([
                'user_id' => 1,
                'recipe_id' => $recipe->id,
            ]);
        }
    }
}
