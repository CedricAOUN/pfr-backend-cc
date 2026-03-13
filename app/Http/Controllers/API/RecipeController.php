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
    if (!$request->user()->is_premium) {
      return response()->json(['message' => 'Only premium users can create recipes.'], 403);
    }
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

  function update(Request $request, Recipe $recipe)
  {
    if ($request->user()->id !== $recipe->creator_id) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }
    $validated = $request->validate([
      'title' => 'string|max:255',
      'description' => 'string',
      'ingredients' => 'array',
      'ingredients.*.name' => 'string',
      'ingredients.*.quantity' => 'numeric',
      'ingredients.*.unit' => 'string',
      'instructions' => 'string',
      'is_premium' => 'boolean',
    ]);
    $recipe->update($validated);
    if (isset($validated['ingredients'])) {
      $recipe->ingredients()->delete();
      foreach ($validated['ingredients'] as $ingredient) {
        $recipe->ingredients()->create($ingredient);
      }
    }
    return new RecipeResource($recipe->load('ingredients'));
  }

  function destroy(Request $request, Recipe $recipe)
  {
    if ($request->user()->id !== $recipe->creator_id) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }
    $recipe->delete();
    return response()->noContent();
  }
}
