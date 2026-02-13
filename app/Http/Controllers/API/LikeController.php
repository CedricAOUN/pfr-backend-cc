<?php

namespace App\Http\API\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Like;
use App\Models\Recipe;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    function toggleLike(Request $request, $recipeId)
    {
        $user = $request->user();
        $like = Like::where('user_id', $user->id)->where('recipe_id', $recipeId)->first();
        if ($like) {
            $like->delete();
        } else {
            Like::create(['user_id' => $user->id, 'recipe_id' => $recipeId,]);
        }
        $recipe = Recipe::withCount('likes')->findOrFail($recipeId);
        return new RecipeResource($recipe);
    }
}
