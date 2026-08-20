<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function update(User $user, Course $course): bool
    {
        return $user->id === $course->expert_id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->id === $course->expert_id;
    }
}
