<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $favoriteRecipeIds = $this->whenLoaded('favorites', fn() => $this->favorites->pluck('recipe_id'));

        $favoriteRecipeCards = [];

        foreach ($favoriteRecipeIds as $recipeId) {
            $recipe = $this->favorites->firstWhere('recipe_id', $recipeId)->recipe;
            $favoriteRecipeCards[] = [
                'id' => $recipeId,
                'title' => $recipe->title,
                'description' => $recipe->description,
                'image_url' => $recipe->image_url,
            ];
        }

        $isChef = $this->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereHas('items', function ($query) {
                $query->where(
                    'stripe_product',
                    config('plans.chef.product')
                );
            })
            ->exists();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'biography' => $this->biography,
            'avatar_url' => $this->avatar_url
                ? (str_starts_with($this->avatar_url, 'http') ? $this->avatar_url : asset($this->avatar_url))
                : null,
            'is_premium' => $this->subscription('default')?->active() ?? false,
            'is_chef' => $isChef,
            'premium_expire' => $this->subscription('default')?->current_period_end,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'favorite_recipes' => $this->whenLoaded('favorites', fn() => $favoriteRecipeCards),
        ];
    }
}
