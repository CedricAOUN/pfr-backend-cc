<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Role gates
        Gate::define('is-premium', fn(User $user) => $user->is_premium);
        Gate::define('is-expert',  fn(User $user) => $user->is_expert);

        // Ownership gates
        Gate::define('modify-recipe', fn(User $user, Recipe $recipe) => $user->id === $recipe->creator_id);
        Gate::define('modify-course', fn(User $user, Course $course) => $user->id === $course->expert_id);
        Gate::define('modify-user',   fn(User $user, User $target)   => $user->id === $target->id);
    }
}
