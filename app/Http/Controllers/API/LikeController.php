<?php

namespace App\Http\Controllers\API;

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

        // Eager-load likes filtered to the current user so the resource
        // can compute `is_liked_by_current_user` from the loaded relation.
        $recipe = Recipe::withCount('likes')
            ->with(['likes' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->findOrFail($recipeId);
        return new RecipeResource($recipe);
    }
}
