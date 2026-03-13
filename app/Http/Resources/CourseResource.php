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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'video_stream_url' => $this->video_path
                ? route('courses.video', $this->id)
                : null,
            'expert' => new UserResource($this->whenLoaded('expert')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
