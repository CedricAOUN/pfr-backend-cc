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
        $user = $request->user();
        $isLikedByCurrentUser = $user && $this->relationLoaded('likes')
            ? $this->likes->contains('user_id', $user->id)
            : false;

        $creator = collect($this->creator)->only(['id', 'name', 'first_name', 'last_name', 'avatar_url']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'ingredients' => IngredientResource::collection($this->whenLoaded('ingredients') ?: collect()),
            'instructions' => $this->instructions,
            'is_premium' => $this->is_premium,
            'image_url' => $this->image_url,
            'creator' => $creator,
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'likes' => [
                "count" => $this->whenCounted('likes'),
                "is_logged_in_user" => $user !== null,
                "is_liked_by_current_user" => $isLikedByCurrentUser,
            ],
            'favorites_count' => $this->whenCounted('favorites'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
