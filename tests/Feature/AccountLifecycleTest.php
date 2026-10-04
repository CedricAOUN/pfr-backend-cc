<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    public function test_password_login_and_logout_revoke_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole('regular_user');
        $otherToken = $user->createToken('other');
        $this->postJson('/api/v1/users/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/v1/users/login', ['email' => 'absent@example.com', 'password' => 'password'])->assertUnauthorized();
        $this->postJson('/api/v1/users/login', [])->assertUnprocessable();
        $response = $this->postJson('/api/v1/users/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->withToken($response->json('access_token'))->getJson('/api/v1/users/me')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->postJson('/api/v1/users/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/users/me')->assertUnauthorized();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }

    public function test_profile_validation_ownership_and_avatar_replacement(): void
    {
        $user = User::factory()->create();
        $user->assignRole('regular_user');
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->putJson("/api/v1/users/edit/{$user->id}", ['name' => 'Stolen'])->assertForbidden();
        Sanctum::actingAs($user);
        $this->putJson("/api/v1/users/edit/{$user->id}", ['name' => $other->name])->assertUnprocessable()->assertJsonValidationErrors('name');
        foreach (['first.jpg', 'second.jpg'] as $filename) {
            $this->put("/api/v1/users/edit/{$user->id}", ['first_name' => 'Julia', 'biography' => 'A chef', 'avatar_url' => UploadedFile::fake()->image($filename)], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.first_name', 'Julia');
            $this->assertCount(1, Storage::disk('public')->allFiles('user_avatars'));
        }
        $this->assertDatabaseHas('users', ['id' => $user->id, 'biography' => 'A chef']);
    }

    public function test_profiles_and_search_keep_private_fields_for_self_only(): void
    {
        $chef = User::factory()->create(['name' => 'julia', 'first_name' => 'Julia']);
        $chef->assignRole('chef');
        $recipe = Recipe::create(['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'creator_id' => $chef->id, 'image_url' => '/storage/soup.jpg']);
        $chef->favorites()->create(['recipe_id' => $recipe->id]);
        $this->getJson("/api/v1/users/{$chef->id}")->assertOk()->assertJsonMissingPath('data.email');
        $this->getJson('/api/v1/users?search=Julia')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/users/chefs?search=Julia')->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.email');
        Sanctum::actingAs($chef);
        $this->getJson("/api/v1/users/{$chef->id}")->assertOk()->assertJsonPath('data.email', $chef->email)->assertJsonPath('data.favorite_recipes.0.id', $recipe->id);
        $this->getJson('/api/v1/users/me')->assertJsonPath('data.is_chef', true)->assertJsonPath('data.is_premium', true);
    }
}
