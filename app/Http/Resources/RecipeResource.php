<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');
        $isLikedByCurrentUser = $user && $this->relationLoaded('likes')
            ? $this->likes->contains('user_id', $user->id)
            : false;

        $isFavoritedByCurrentUser = $user && $this->relationLoaded('favorites')
            ? $this->favorites->contains('user_id', $user->id)
            : false;

        $creator = collect($this->creator)->only(['id', 'name', 'first_name', 'last_name', 'avatar_url']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'ingredients' => IngredientResource::collection($this->whenLoaded('ingredients') ?: collect()),
            'instructions' => $this->instructions,
            'is_premium' => $this->is_premium,
            'image_url' => $this->image_url
                ? (str_starts_with($this->image_url, 'http') ? $this->image_url : asset($this->image_url))
                : null,
            'creator' => $creator,
            'comments' => CommentResource::collection($this->whenLoaded('comments')->sortByDesc('created_at')),
            'likes' => [
                'count' => $this->whenCounted('likes'),
                'is_liked_by_user' => $isLikedByCurrentUser,
            ],
            'favorites' => [
                'count' => $this->whenCounted('favorites'),
                'is_favorited_by_user' => $isFavoritedByCurrentUser,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
