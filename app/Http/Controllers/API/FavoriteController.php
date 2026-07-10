<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Favorite;
use App\Models\Recipe;

class FavoriteController extends Controller
{
    function toggleFavorite(Request $request, $recipeId)
    {
        $user = $request->user();
        $favorite = Favorite::where('user_id', $user->id)->where('recipe_id', $recipeId)->first();
        if ($favorite) {
            $favorite->delete();
        } else {
            Favorite::create(['user_id' => $user->id, 'recipe_id' => $recipeId,]);
        }

        // Eager-load favorites filtered to the current user so the resource
        // can compute `is_favorited_by_user` from the loaded relation.
        $recipe = Recipe::withCount('favorites')
            ->with(['favorites' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->findOrFail($recipeId);
        return new RecipeResource($recipe);
    }
}
