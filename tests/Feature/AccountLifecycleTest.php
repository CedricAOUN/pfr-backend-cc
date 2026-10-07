<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\Course;
use App\Models\Comment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_account_cascades_its_data_and_removes_only_its_media(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['avatar_url' => '/storage/user_avatars/owner.jpg']);
        $owner->assignRole('chef');
        $other = User::factory()->create();
        $recipe = Recipe::create(['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'creator_id' => $owner->id, 'image_url' => url('/storage/recipe_images/owner.jpg')]);
        $kept = Recipe::create(['title' => 'Cake', 'description' => 'Sweet', 'instructions' => 'Bake', 'creator_id' => $other->id, 'image_url' => '/storage/recipe_images/other.jpg']);
        $recipe->ingredients()->create(['name' => 'Salt', 'quantity' => 1, 'unit' => 'g']);
        $recipe->suggestion()->create(['suggestion' => 'Boil carefully']);
        $recipe->likes()->create(['user_id' => $other->id]);
        $recipe->favorites()->create(['user_id' => $other->id]);
        $kept->likes()->create(['user_id' => $owner->id]);
        $kept->favorites()->create(['user_id' => $owner->id]);
        Comment::create(['creator_id' => $owner->id, 'recipe_id' => $kept->id, 'content' => 'Nice']);
        Comment::create(['creator_id' => $other->id, 'recipe_id' => $recipe->id, 'content' => 'Great']);
        Course::create(['title' => 'Lesson', 'expert_id' => $owner->id, 'video_path' => '/storage/course_videos/owner.mp4']);
        Course::create(['title' => 'Legacy', 'expert_id' => $owner->id, 'video_path' => 'course_videos/legacy.mp4']);
        Course::create(['title' => 'Other', 'expert_id' => $other->id, 'video_path' => '/storage/course_videos/other.mp4']);
        $owner->createToken('auth');
        DB::table('sessions')->insert(['id' => 'owner-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $owner->email, 'token' => 'reset']);
        $subscription = $owner->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_delete', 'stripe_status' => 'canceled']);
        $subscription->items()->create(['stripe_id' => 'si_delete', 'stripe_product' => 'prod_test', 'stripe_price' => 'price_test']);
        foreach (['user_avatars/owner.jpg', 'recipe_images/owner.jpg', 'recipe_images/other.jpg', 'course_videos/owner.mp4', 'course_videos/other.mp4'] as $path) {
            Storage::disk('public')->put($path, 'media');
        }
        Storage::disk('local')->put('course_videos/legacy.mp4', 'media');
        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/users/delete/{$owner->id}", ['password' => 'password'])->assertForbidden();
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/users/delete/{$owner->id}", ['password' => 'wrong'])->assertUnauthorized();
        Storage::disk('public')->assertExists('user_avatars/owner.jpg');
        $this->deleteJson("/api/v1/users/delete/{$owner->id}", ['password' => 'password'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('recipes', ['id' => $kept->id]);
        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseCount('courses', 1);
        foreach (['comments', 'ingredients', 'suggestions', 'likes', 'favorites', 'personal_access_tokens', 'sessions', 'password_reset_tokens', 'subscriptions', 'subscription_items'] as $table) {
            $this->assertDatabaseEmpty($table);
        }
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $owner->id, 'model_type' => $owner->getMorphClass()]);
        Storage::disk('public')->assertMissing(['user_avatars/owner.jpg', 'recipe_images/owner.jpg', 'course_videos/owner.mp4']);
        Storage::disk('local')->assertMissing('course_videos/legacy.mp4');
        Storage::disk('public')->assertExists(['recipe_images/other.jpg', 'course_videos/other.mp4']);
    }

    public function test_database_itself_cascades_user_content(): void
    {
        $owner = User::factory()->create();
        $recipe = Recipe::create(['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'creator_id' => $owner->id]);
        Comment::create(['creator_id' => $owner->id, 'recipe_id' => $recipe->id, 'content' => 'Nice']);
        Course::create(['title' => 'Lesson', 'expert_id' => $owner->id]);
        DB::table('users')->where('id', $owner->id)->delete();
        foreach (['recipes', 'comments', 'courses'] as $table) {
            $this->assertDatabaseEmpty($table);
        }
    }

    public function test_external_media_urls_do_not_delete_local_files(): void
    {
        $owner = User::factory()->create(['avatar_url' => 'https://external.example/storage/user_avatars/shared.jpg']);
        Recipe::create(['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'creator_id' => $owner->id, 'image_url' => 'https://external.example/storage/recipe_images/shared.jpg']);
        Storage::disk('public')->put('user_avatars/shared.jpg', 'keep');
        Storage::disk('public')->put('recipe_images/shared.jpg', 'keep');
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/users/delete/{$owner->id}", ['password' => 'password'])->assertNoContent();
        Storage::disk('public')->assertExists(['user_avatars/shared.jpg', 'recipe_images/shared.jpg']);
    }

    public function test_storage_failure_keeps_the_account_and_its_media_references(): void
    {
        $owner = User::factory()->create(['avatar_url' => '/storage/user_avatars/owner.jpg']);
        $owner->createToken('auth');
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with('user_avatars/owner.jpg')->andReturn(true);
        $disk->shouldReceive('delete')->once()->with('user_avatars/owner.jpg')->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/users/delete/{$owner->id}", ['password' => 'password'])->assertStatus(500);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'avatar_url' => '/storage/user_avatars/owner.jpg']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

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
