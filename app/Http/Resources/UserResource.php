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
        $favoriteRecipeCards = [];

        if ($this->relationLoaded('favorites')) {
            foreach ($this->favorites as $favorite) {
                $recipe = $favorite->recipe;
                $favoriteRecipeCards[] = [
                    'id' => $recipe->id,
                    'title' => $recipe->title,
                    'description' => $recipe->description,
                    'image_url' => $recipe->image_url
                        ? (str_starts_with($recipe->image_url, 'http') ? $recipe->image_url : asset($recipe->image_url))
                        : null,
                ];
            }
        }

        $isChef = $this->hasRole('chef') || $this->hasRole('admin');
        $isPremium = $this->hasAnyRole(['premium_user', 'chef', 'admin']);
        $subscription = $this->subscription('default');

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
            'is_premium' => $isPremium,
            'is_chef' => $isChef,
            'premium_expire' => $subscription?->current_period_end,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'favorite_recipes' => $this->whenLoaded('favorites', fn() => $favoriteRecipeCards),
        ];
    }
}
