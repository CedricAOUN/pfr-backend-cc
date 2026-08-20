<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'biography' => $this->biography,
            'avatar_url' => $this->avatar_url,
            'is_chef' => $this->hasRole('chef'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
