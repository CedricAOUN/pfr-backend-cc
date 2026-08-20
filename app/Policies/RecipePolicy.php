<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(?User $user, Recipe $recipe): bool
    {
        if (! $recipe->is_premium) {
            return true;
        }

        return $user !== null
            && ($user->id === $recipe->creator_id || $user->can('premium-recipes.view'));
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->creator_id;
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->creator_id;
    }
}
