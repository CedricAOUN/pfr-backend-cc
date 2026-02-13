<?php

namespace Database\Seeders;

use App\Models\Like;
use App\Models\Recipe;
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
                'amount' => rand(5, 150),
                'recipe_id' => $recipe->id,
            ]);
        }
    }
}
