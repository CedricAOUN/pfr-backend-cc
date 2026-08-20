<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'is_premium' => $this->is_premium,
            'image_url' => $this->image_url
                ? (str_starts_with($this->image_url, 'http') ? $this->image_url : asset($this->image_url))
                : null,
            'creator' => collect($this->creator)->only(['id', 'name', 'first_name', 'last_name', 'avatar_url']),
            'likes' => ['count' => $this->whenCounted('likes')],
            'favorites' => ['count' => $this->whenCounted('favorites')],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
