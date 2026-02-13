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

        $creator = [
            'id' => $this->creator->id,
            'name' => $this->creator->name,
            'first_name' => $this->creator->first_name,
            'last_name' => $this->creator->last_name,
            'avatar_url' => $this->creator->avatar_url,
        ];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'ingredients' => json_decode($this->ingredients, true),
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
