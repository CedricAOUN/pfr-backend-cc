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
        $users = User::all();

        foreach ($recipes as $recipe) {
            Like::create([
                'user_id' => 1,
                'recipe_id' => $recipe->id,
            ]);
        }

        foreach ($users as $user) {
            $existingLike = Like::where('user_id', $user->id)->where('recipe_id', 1)->first();
            if ($existingLike) {
                continue;
            }
            Like::create([
                'user_id' => $user->id,
                'recipe_id' => 1,
            ]);
        }
    }
}
