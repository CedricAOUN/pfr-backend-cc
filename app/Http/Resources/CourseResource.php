<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $videoPath = $this->video_path
            ? parse_url($this->video_path, PHP_URL_PATH)
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'video_url' => is_string($videoPath)
                ? asset($videoPath)
                : null,
            'created_by' => new PublicUserResource($this->whenLoaded('expert')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
