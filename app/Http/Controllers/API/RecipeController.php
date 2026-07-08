<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
    if ($request->has('ingredients')) {
      $ingredients = explode(',', $request->input('ingredients'));
      $query->whereHas('ingredients', function ($ingredientQuery) use ($ingredients) {
        $ingredientQuery->whereIn('name', $ingredients);
      });
    }

    if ($request->has('creators')) {
      $creators = explode(',', $request->input('creators'));
      $query->whereHas('creator', function ($creatorQuery) use ($creators) {
        $creatorQuery->whereIn('name', $creators);
      });
    }

    if ($request->has('is_premium')) {
      $isPremium = filter_var($request->input('is_premium'), FILTER_VALIDATE_BOOLEAN);
      $query->where('is_premium', $isPremium);
    }

    return [
      'recipes' => RecipeResource::collection($query->get()),
      'total' => $query->count(),
      'highest_likes' => $query->orderByDesc('likes_count')->first()?->likes_count ?? 0,
      'lowest_likes' => $query->orderBy('likes_count')->first()?->likes_count ?? 0,
      'all_ingredients' => Recipe::with('ingredients')->get()->pluck('ingredients.*.name')->flatten()->unique()->values(),
      'all_creators' => Recipe::with('creator')->get()->pluck('creator.name')->unique()->values(),
    ];
  }

  function show(Recipe $recipe)
  {
    if ($recipe->is_premium) {
      Gate::authorize('is-premium');
    }
    return new RecipeResource($recipe->load(['creator', 'comments.creator', 'likes', 'favorites', 'ingredients'])->loadCount(['likes', 'favorites']));
  }

  function store(Request $request)
  {
    Gate::authorize('is-premium');
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
    Gate::authorize('modify-recipe', $recipe);
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
    Gate::authorize('modify-recipe', $recipe);
    $recipe->delete();
    return response()->noContent();
  }
}
