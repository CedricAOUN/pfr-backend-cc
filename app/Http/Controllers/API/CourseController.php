<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;

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
}
