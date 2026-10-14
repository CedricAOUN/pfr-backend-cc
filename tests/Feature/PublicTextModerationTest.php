<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Course;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicTextModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_and_course_creation_and_edits_reject_each_public_field(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $chef = User::factory()->create();
        $chef->assignRole('chef');
        Sanctum::actingAs($chef);
        $payload = ['title' => 'Soup', 'language' => 'en', 'category' => 'starter', 'duration' => 30,
            'description' => 'Warm soup', 'instructions' => 'Boil', 'ingredients' => [['name' => 'Carrot', 'quantity' => 2, 'unit' => 'pcs']]];
        $recipe = Recipe::create([...$payload, 'creator_id' => $chef->id]);
        foreach (['title', 'description', 'instructions', 'ingredients.0.name'] as $field) {
            $bad = $payload;
            data_set($bad, $field, 'f.u.c.k');
            $this->postJson('/api/v1/recipes/create', $bad)->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->putJson('/api/v1/recipes/edit/'.$recipe->id, $bad)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $course = Course::create(['title' => 'Knife skills', 'language' => 'en', 'expert_id' => $chef->id]);
        foreach (['title', 'description', 'content'] as $field) {
            $this->postJson('/api/v1/courses/create', ['title' => 'Course', 'language' => 'fr', $field => 'sh1t'])->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->putJson('/api/v1/courses/edit/'.$course->id, [$field => 'putain'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'title' => 'Soup']);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Knife skills']);
    }

    public function test_comments_and_public_profile_fields_are_moderated_without_altering_identity_fields(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('chef');
        Sanctum::actingAs($user);
        $recipe = Recipe::create(['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'creator_id' => $user->id]);
        $comment = Comment::create(['content' => 'Lovely soup', 'recipe_id' => $recipe->id, 'creator_id' => $user->id]);
        $this->postJson('/api/v1/comments/create', ['recipe_id' => $recipe->id, 'content' => 'connard'])->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->putJson('/api/v1/comments/edit/'.$comment->id, ['content' => 'asshole'])->assertUnprocessable()->assertJsonValidationErrors('content');
        foreach (['name', 'biography'] as $field) {
            $this->putJson('/api/v1/users/edit/'.$user->id, [$field => 'fucker'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->putJson('/api/v1/users/edit/'.$user->id, ['first_name' => 'Dick', 'last_name' => 'Martin', 'biography' => 'I cook chicken breast and shiitake mushrooms.'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Dick', 'last_name' => 'Martin']);
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'content' => 'Lovely soup']);
    }

    public function test_registration_moderates_only_the_public_username(): void
    {
        $this->postJson('/api/v1/users/register', ['name' => 'fucker', 'email' => 'person@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_private_email_and_password_values_are_not_moderated(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
        $this->mock(UncompromisedVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->andReturn(true));
        $this->postJson('/api/v1/users/register', [
            'name' => 'friendly_cook', 'email' => 'shit@example.com',
            'password' => 'StrongFuckPassword#2026', 'password_confirmation' => 'StrongFuckPassword#2026',
        ])->assertSuccessful();
        $this->assertDatabaseHas('users', ['name' => 'friendly_cook', 'email' => 'shit@example.com']);
    }
}
