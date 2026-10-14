<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Rules\AllowedPublicText;
use App\Http\Resources\RecipeCardResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\SuggestionResource;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use App\Support\IntegrationLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use LucianoTonet\GroqLaravel\Facades\Groq;
use RuntimeException;
use Throwable;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'category' => ['sometimes', Rule::in(Recipe::CATEGORIES)],
            'language' => ['sometimes', 'required', Rule::in(['en', 'fr'])],
        ]);
        $query = Recipe::with('creator')
            ->withCount(['likes', 'favorites']);

        if (isset($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (isset($validated['language'])) {
            $query->where('language', $validated['language']);
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('ingredients', function ($ingredientQuery) use ($search) {
                        $ingredientQuery->where('name', 'like', '%'.$search.'%');
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

        if ($request->has('recipeType')) {
            switch ($request->input('recipeType')) {
                case 'all':
                    break;
                case 'premium':
                    $query->where('is_premium', true);
                    break;
                case 'free':
                    $query->where('is_premium', false);
                    break;
            }
        }

        if ($request->filled('likeRange')) {
            [$minLikes, $maxLikes] = array_map(
                'intval',
                explode(',', $request->input('likeRange'))
            );

            $query
                ->has('likes', '>=', $minLikes)
                ->has('likes', '<=', $maxLikes);
        }

        $recipes = $query->get();

        // Global min/max across ALL recipes, independent of filters
        $likesCounts = Recipe::withCount('likes')->pluck('likes_count');

        return [
            'recipes' => RecipeCardResource::collection($recipes),
            'total' => $recipes->count(),
            'highest_likes' => $likesCounts->max() ?? 0,
            'lowest_likes' => $likesCounts->min() ?? 0,
            'all_ingredients' => Ingredient::query()->distinct()->orderBy('name')->pluck('name'),
            'all_creators' => User::query()
                ->whereIn('id', Recipe::query()->select('creator_id')->distinct())
                ->orderBy('name')
                ->pluck('name'),
        ];
    }

    public function show(Recipe $recipe)
    {
        $this->authorizeForUser(auth('sanctum')->user(), 'view', $recipe);

        return new RecipeResource($recipe->load(['creator', 'comments.creator', 'likes', 'favorites', 'ingredients', 'suggestion'])->loadCount(['likes', 'favorites']));
    }

    public function store(Request $request)
    {
        $user = auth('sanctum')->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', new AllowedPublicText],
            'category' => ['required', Rule::in(Recipe::CATEGORIES)],
            'language' => ['required', Rule::in(['en', 'fr'])],
            'duration' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'description' => ['required', 'string', new AllowedPublicText],
            'ingredients' => 'required|array',
            'ingredients.*.name' => ['required', 'string', new AllowedPublicText],
            'ingredients.*.quantity' => 'required|numeric',
            'ingredients.*.unit' => 'required|string',
            'instructions' => ['required', 'string', new AllowedPublicText],
            'image_file' => 'nullable|image|max:2048', // max 2MB
            'is_premium' => 'boolean',
        ]);
        if (($validated['is_premium'] ?? false) && $user instanceof User && ! $user->hasPermissionTo('premium-recipes.create')) {
            return response()->json(['message' => 'You do not have permission to create premium recipes.'], 403);
        }

        $recipe = Recipe::create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'language' => $validated['language'],
            'duration' => $validated['duration'],
            'description' => $validated['description'],
            'instructions' => $validated['instructions'],
            'is_premium' => $validated['is_premium'] ?? false,
            'creator_id' => $request->user()->id,
        ]);

        foreach ($validated['ingredients'] as $ingredient) {
            $recipe->ingredients()->create($ingredient);
        }

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('recipe_images', 'public');
            $recipe->image_url = Storage::url($path);
            $recipe->save();
        } else {
            $recipe->image_url = 'https://placehold.co/400x400.png?text=No+Image';
            $recipe->save();
        }

        return new RecipeResource($recipe->load('ingredients'));
    }

    public function update(Request $request, Recipe $recipe)
    {
        $this->authorize('update', $recipe);

        $validated = $request->validate([
            'title' => ['string', 'max:255', new AllowedPublicText],
            'category' => ['sometimes', 'required', Rule::in(Recipe::CATEGORIES)],
            'language' => ['sometimes', 'required', Rule::in(['en', 'fr'])],
            'duration' => ['sometimes', 'required', 'integer', 'min:1', 'max:4294967295'],
            'description' => ['string', new AllowedPublicText],
            'ingredients' => 'array',
            'ingredients.*.name' => ['string', new AllowedPublicText],
            'ingredients.*.quantity' => 'numeric',
            'ingredients.*.unit' => 'string',
            'instructions' => ['string', new AllowedPublicText],
            'image_file' => 'nullable|image|max:2048', // max 2MB
            'is_premium' => 'boolean',
        ]);

        $promotesToPremium = ! $recipe->is_premium && ($validated['is_premium'] ?? false);
        if ($promotesToPremium && ! $request->user()->can('premium-recipes.create')) {
            return response()->json(['message' => 'You do not have permission to create premium recipes.'], 403);
        }

        $recipe->update($validated);
        if (isset($validated['ingredients'])) {
            $recipe->ingredients()->delete();
            foreach ($validated['ingredients'] as $ingredient) {
                $recipe->ingredients()->create($ingredient);
            }
        }

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('recipe_images', 'public');
            $recipe->image_url = Storage::url($path);
            $recipe->save();
        }

        return new RecipeResource($recipe->load('ingredients'));
    }

    public function destroy(Request $request, Recipe $recipe)
    {
        $this->authorize('delete', $recipe);

        $recipe->delete();

        return response()->noContent();
    }

    public function toggleLike(Request $request, Recipe $recipe)
    {
        $user = auth('sanctum')->user();
        if ($recipe->likes()->where('user_id', $user->id)->exists()) {
            $recipe->likes()->detach($user->id);

            return response()->json(['message' => 'Recipe unliked.']);
        } else {
            $recipe->likes()->attach($user->id);

            return response()->json(['message' => 'Recipe liked.']);
        }
    }

    public function toggleFavorite(Request $request, Recipe $recipe)
    {
        $user = auth('sanctum')->user();
        if ($recipe->favorites()->where('user_id', $user->id)->exists()) {
            $recipe->favorites()->detach($user->id);

            return response()->json(['message' => 'Recipe removed from favorites.']);
        } else {
            $recipe->favorites()->attach($user->id);

            return response()->json(['message' => 'Recipe added to favorites.']);
        }
    }

    public function askAI(Request $request, Recipe $recipe)
    {
        $startedAt = microtime(true);
        $context = IntegrationLog::context([
            'recipe_id' => $recipe->id,
            'user_id' => auth('sanctum')->id(),
            'model' => config('groq.model'),
            'api_host' => parse_url((string) config('groq.api_base'), PHP_URL_HOST),
            'api_key_configured' => (bool) config('groq.api_key'),
            'timeout_seconds' => config('groq.timeout'),
            'max_tokens' => config('groq.options.max_tokens', 150),
        ]);
        IntegrationLog::write('info', 'ai.request.started', $context);
        try {
            $this->authorizeForUser(auth('sanctum')->user(), 'view', $recipe);
        } catch (AuthorizationException $exception) {
            IntegrationLog::write('warning', 'ai.request.denied', [
                ...$context, 'duration_ms' => IntegrationLog::duration($startedAt),
            ]);
            throw $exception;
        }

        $stage = 'load_suggestion';
        try {
            $existingSuggestion = $recipe->suggestion()->first();

            if ($existingSuggestion) {
                IntegrationLog::write('info', 'ai.request.cached', [
                    ...$context, 'suggestion_id' => $existingSuggestion->id, 'duration_ms' => IntegrationLog::duration($startedAt),
                ]);
                return new SuggestionResource($existingSuggestion);
            }

            $stage = 'load_ingredients';
            $recipe->loadMissing('ingredients');
        } catch (Throwable $exception) {
            IntegrationLog::write('error', 'ai.request.failed', [
                ...$context, 'stage' => $stage, 'duration_ms' => IntegrationLog::duration($startedAt),
                ...IntegrationLog::error($exception),
            ]);
            throw $exception;
        }

        $ingredients = $recipe->ingredients
            ->map(fn (Ingredient $ingredient) => sprintf(
                '- %s %s %s',
                $ingredient->quantity,
                $ingredient->unit,
                $ingredient->name,
            ))
            ->implode("\n");

        $stage = 'provider_request';
        try {
            IntegrationLog::write('info', 'ai.provider.started', [
                ...$context, 'ingredients_count' => $recipe->ingredients->count(),
                'instructions_characters' => mb_strlen($recipe->instructions),
            ]);
            $response = Groq::chat()->completions()->create([
                'model' => config('groq.model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => config('groq.system_prompt'),
                    ],
                    [
                        'role' => 'user',
                        'content' => "Ingredients:\n{$ingredients}\n\nSteps:\n{$recipe->instructions}",
                    ],
                ],
                'temperature' => 0.2,
                'max_tokens' => config('groq.options.max_tokens', 150),
            ]);

            $stage = 'validate_response';
            $summary = trim((string) data_get($response, 'choices.0.message.content'));
            IntegrationLog::write('info', 'ai.provider.completed', [
                ...$context, 'duration_ms' => IntegrationLog::duration($startedAt),
                'response_id' => data_get($response, 'id'),
                'finish_reason' => data_get($response, 'choices.0.finish_reason'),
                'total_tokens' => data_get($response, 'usage.total_tokens'),
                'output_characters' => mb_strlen($summary),
            ]);

            if ($summary === '') {
                throw new RuntimeException('Groq returned an empty recipe summary.');
            }

            $stage = 'persist_suggestion';
            $suggestion = $recipe->suggestion()->firstOrCreate([], [
                'suggestion' => $summary,
            ]);
        } catch (Throwable $exception) {
            IntegrationLog::write('error', 'ai.request.failed', [
                ...$context, 'stage' => $stage, 'duration_ms' => IntegrationLog::duration($startedAt),
                ...IntegrationLog::error($exception),
            ]);

            return response()->json([
                'message' => 'Unable to generate the recipe suggestion right now.',
            ], 502);
        }

        IntegrationLog::write('info', 'ai.request.completed', [
            ...$context, 'suggestion_id' => $suggestion->id, 'duration_ms' => IntegrationLog::duration($startedAt),
        ]);
        return (new SuggestionResource($suggestion))
            ->response()
            ->setStatusCode(200);
    }
}
