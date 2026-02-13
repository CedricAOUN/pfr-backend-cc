<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
  function index(Request $request)
  {
    $query = Recipe::with(['creator', 'comments.creator', 'likes', 'favorites'])
      ->withCount(['likes', 'favorites']);

    if ($request->has('search')) {
      $search = $request->input('search');
      $query->where(function ($q) use ($search) {
        $q->where('title', 'like', '%' . $search . '%')
          ->orWhere('description', 'like', '%' . $search . '%')
          ->orWhere('ingredients', 'like', '%' . $search . '%');
      });
    }

    return RecipeResource::collection($query->get());
  }
  function show(Recipe $recipe)
  {
    $user = auth('sanctum')->user();
    if ($recipe->is_premium && (!$user || !$user->is_premium)) {
      return response()->json(['message' => 'This recipe is premium and cannot be accessed without a subscription.'], 403);
    }
    return new RecipeResource($recipe->load(['creator', 'comments.creator', 'likes', 'favorites'])->loadCount(['likes', 'favorites']));
  }
}
