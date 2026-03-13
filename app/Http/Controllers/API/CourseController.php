<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
  function index(Request $request)
  {
    $query = Course::with('expert');

    if ($request->has('search')) {
      $search = $request->input('search');
      $query->where(function ($q) use ($search) {
        $q->where('title', 'like', '%' . $search . '%')
          ->orWhere('description', 'like', '%' . $search . '%');
      });
    }

    return CourseResource::collection($query->get());
  }

  function show(Course $course)
  {
    return new CourseResource($course->load('expert'));
  }

  function store(Request $request)
  {
    Gate::authorize('is-expert');

    $validated = $request->validate([
      'title'      => 'required|string|max:255',
      'description' => 'required|string',
      'content'    => 'sometimes|nullable|string',
      'video'      => 'nullable|file|mimes:mp4,mov,avi,wmv|max:204800', // 200 MB
    ]);

    $videoPath = null;
    if ($request->hasFile('video')) {
      $videoPath = $request->file('video')->store('course_videos', 'local');
    }

    $course = Course::create([
      'title'       => $validated['title'],
      'description' => $validated['description'],
      'content'     => $validated['content'] ?? null,
      'video_path'  => $videoPath,
      'expert_id'   => $request->user()->id,
    ]);

    return new CourseResource($course);
  }

  function update(Request $request, Course $course)
  {
    Gate::authorize('modify-course', $course);

    $validated = $request->validate([
      'title'       => 'sometimes|required|string|max:255',
      'description' => 'sometimes|required|string',
      'content'     => 'sometimes|nullable|string',
      'video'      => 'nullable|file|mimes:mp4,mov,avi,wmv|max:204800', // 200 MB
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
      if ($course->video_path && Storage::disk('local')->exists($course->video_path)) {
        Storage::disk('local')->delete($course->video_path);
      }
      // Store new video
      $course->video_path = $request->file('video')->store('course_videos', 'local');
    }

    $course->save();

    return new CourseResource($course);
  }

  function destroy(Request $request, Course $course)
  {
    Gate::authorize('modify-course', $course);
    // Delete video file if exists
    if ($course->video_path && Storage::disk('local')->exists($course->video_path)) {
      Storage::disk('local')->delete($course->video_path);
    }
    $course->delete();
    return response()->noContent();
  }

  function streamVideo(Request $request, Course $course)
  {
    Gate::authorize('is-premium');

    if (!$course->video_path || !Storage::disk('local')->exists($course->video_path)) {
      return response()->json(['message' => 'Video not found.'], 404);
    }

    $fullPath = Storage::disk('local')->path($course->video_path);
    $mimeType = mime_content_type($fullPath) ?: 'video/mp4';

    return response()->file($fullPath, [
      'Content-Type'        => $mimeType,
      'Content-Disposition' => 'inline',
    ]);
  }
}
