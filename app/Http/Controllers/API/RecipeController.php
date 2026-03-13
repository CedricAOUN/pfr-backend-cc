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
    $query = Recipe::with(['creator', 'comments.creator', 'likes', 'favorites', 'ingredients'])
      ->withCount(['likes', 'favorites']);

    if ($request->has('search')) {
      $search = $request->input('search');
      $query->where(function ($q) use ($search) {
        $q->where('title', 'like', '%' . $search . '%')
          ->orWhere('description', 'like', '%' . $search . '%')
          ->orWhereHas('ingredients', function ($ingredientQuery) use ($search) {
            $ingredientQuery->where('name', 'like', '%' . $search . '%');
          });
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
    return new RecipeResource($recipe->load(['creator', 'comments.creator', 'likes', 'favorites', 'ingredients'])->loadCount(['likes', 'favorites']));
  }

  function store(Request $request)
  {
    $validated = $request->validate([
      'title' => 'required|string|max:255',
      'description' => 'required|string',
      'ingredients' => 'required|array',
      'ingredients.*.name' => 'required|string',
      'ingredients.*.quantity' => 'required|numeric',
      'ingredients.*.unit' => 'required|string',
      'instructions' => 'required|string',
      'is_premium' => 'boolean',
    ]);
    $recipe = Recipe::create(array_merge($validated, ['creator_id' => $request->user()->id]));
    foreach ($validated['ingredients'] as $ingredient) {
      $recipe->ingredients()->create($ingredient);
    }
    return new RecipeResource($recipe->load('ingredients'));
  }
}
