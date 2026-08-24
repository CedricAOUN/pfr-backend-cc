<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\RedactedCourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with('expert');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }
        if ($request->has('creator_id')) {
            $creatorId = $request->input('creator_id');
            $query->where('expert_id', $creatorId);
        }

        return RedactedCourseResource::collection($query->get());
    }

    public function show(Course $course)
    {
        return new CourseResource($course->load('expert'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'content' => 'sometimes|nullable|string',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:204800', // 200 MB
        ]);

        $videoPath = null;
        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('course_videos', 'public');
            $videoPath = '/storage/'.$path;
        }

        $course = Course::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'] ?? null,
            'video_path' => $videoPath,
            'expert_id' => $request->user()->id,
        ]);

        return new CourseResource($course);
    }

    public function update(Request $request, Course $course)
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'content' => 'sometimes|nullable|string',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:204800', // 200 MB
        ]);

        if (isset($validated['title'])) {
            $course->title = $validated['title'];
        }
        if (isset($validated['description'])) {
            $course->description = $validated['description'];
        }
        if (array_key_exists('content', $validated)) {
            $course->content = $validated['content'];
        }
        if ($request->hasFile('video')) {
            // Delete old video if exists
            if ($course->video_path) {
                $oldPath = str_replace('/storage/', '', parse_url($course->video_path, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }
            // Store new video
            $path = $request->file('video')->store('course_videos', 'public');
            $course->video_path = '/storage/'.$path;
        }

        $course->save();

        return new CourseResource($course);
    }

    public function destroy(Request $request, Course $course)
    {
        $this->authorize('delete', $course);

        // Delete video file if exists
        if ($course->video_path) {
            $videoPath = str_replace('/storage/', '', parse_url($course->video_path, PHP_URL_PATH));
            Storage::disk('public')->delete($videoPath);
        }
        $course->delete();

        return response()->noContent();
    }

    public function streamVideo(Request $request, Course $course)
    {
        $videoPath = $course->video_path
            ? str_replace('/storage/', '', parse_url($course->video_path, PHP_URL_PATH))
            : null;

        if (! $videoPath || ! Storage::disk('public')->exists($videoPath)) {
            return response()->json(['message' => 'Video not found.'], 404);
        }

        $fullPath = Storage::disk('public')->path($videoPath);
        $mimeType = mime_content_type($fullPath) ?: 'video/mp4';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
        ]);
    }
    public function listCourses(Request $request)
    {
        $query = Course::with('expert');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        return RedactedCourseResource::collection($query->get());
    }
}
