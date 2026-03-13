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
            'is_premium' => $this->is_premium,
            'is_expert' => $this->is_expert,
            'premium_expire' => $this->premium_expire,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'favorite_recipes' => $this->whenLoaded('favorites', fn() => $favoriteRecipeCards),
        ];
    }
}
