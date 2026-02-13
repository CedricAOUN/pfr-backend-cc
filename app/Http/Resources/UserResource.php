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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'biography' => $this->biography,
            'avatar_url' => $this->avatar_url,
            'is_premium' => $this->is_premium,
            'is_expert' => $this->is_expert,
            'premium_expire' => $this->premium_expire,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
