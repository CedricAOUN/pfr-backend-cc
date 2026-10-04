<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function user(string $role = 'chef'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function recipe(User $owner, array $attributes = []): Recipe
    {
        return Recipe::create(array_merge(['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'creator_id' => $owner->id, 'is_premium' => false], $attributes));
    }

    public function test_recipe_filters_and_global_metadata(): void
    {
        $chef = $this->user();
        $other = $this->user();
        $soup = $this->recipe($chef);
        $soup->ingredients()->create(['name' => 'Carrot', 'quantity' => 2, 'unit' => 'pcs']);
        $premium = $this->recipe($other, ['title' => 'Cake', 'is_premium' => true]);
        $soup->likes()->create(['user_id' => $chef->id]);
        foreach (['search=Soup', 'search=Carrot', 'ingredients=Carrot', 'creators='.urlencode($chef->name), 'recipeType=free', 'likeRange=1,2'] as $filter) {
            $this->getJson('/api/v1/recipes?'.$filter)->assertOk()->assertJsonCount(1, 'recipes')->assertJsonPath('recipes.0.id', $soup->id)->assertJsonPath('highest_likes', 1)->assertJsonPath('lowest_likes', 0);
        }
        $this->getJson('/api/v1/recipes?recipeType=premium')->assertJsonPath('recipes.0.id', $premium->id);
        $this->getJson('/api/v1/recipes?search=absent')->assertJsonPath('total', 0)->assertJsonPath('all_ingredients.0', 'Carrot');
    }

    public function test_recipe_upload_and_ingredient_replacement(): void
    {
        $chef = $this->user();
        Sanctum::actingAs($chef);
        $this->postJson('/api/v1/recipes/create', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'ingredients', 'instructions']);
        $response = $this->post('/api/v1/recipes/create', ['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'ingredients' => [['name' => 'Carrot', 'quantity' => 2, 'unit' => 'pcs']], 'image_file' => UploadedFile::fake()->image('soup.jpg')], ['Accept' => 'application/json'])->assertSuccessful();
        $id = $response->json('data.id');
        $this->assertCount(1, Storage::disk('public')->allFiles('recipe_images'));
        $this->putJson("/api/v1/recipes/edit/{$id}", ['ingredients' => [['name' => 'Salt', 'quantity' => 1, 'unit' => 'tsp']]])->assertOk()->assertJsonPath('data.ingredients.0.name', 'Salt');
        $this->assertDatabaseMissing('ingredients', ['recipe_id' => $id, 'name' => 'Carrot']);
        $this->put("/api/v1/recipes/edit/{$id}", ['image_file' => UploadedFile::fake()->image('new.jpg')], ['Accept' => 'application/json'])->assertOk();
    }

    public function test_likes_and_favorites_toggle_independently_for_each_user(): void
    {
        $owner = $this->user('regular_user');
        $recipe = $this->recipe($owner);
        Sanctum::actingAs($owner);
        foreach (['like', 'favorite'] as $action) {
            $this->postJson("/api/v1/recipes/{$recipe->id}/{$action}")->assertOk();
        }
        $this->getJson("/api/v1/recipes/{$recipe->id}")->assertJsonPath('data.likes.is_liked_by_user', true)->assertJsonPath('data.favorites.is_favorited_by_user', true);
        Sanctum::actingAs($this->user('regular_user'));
        $this->getJson("/api/v1/recipes/{$recipe->id}")->assertJsonPath('data.likes.is_liked_by_user', false)->assertJsonPath('data.likes.count', 1);
        Sanctum::actingAs($owner);
        foreach (['like', 'favorite'] as $action) {
            $this->postJson("/api/v1/recipes/{$recipe->id}/{$action}")->assertOk();
        }
        $this->assertDatabaseEmpty('likes');
        $this->assertDatabaseEmpty('favorites');
    }

    public function test_comments_validate_and_use_authenticated_creator(): void
    {
        $chef = $this->user();
        $recipe = $this->recipe($chef);
        Sanctum::actingAs($chef);
        $this->postJson('/api/v1/comments/create', ['content' => '', 'recipe_id' => 999])->assertUnprocessable()->assertJsonValidationErrors(['content', 'recipe_id']);
        $response = $this->postJson('/api/v1/comments/create', ['content' => 'Lovely', 'recipe_id' => $recipe->id, 'creator_id' => 999])->assertSuccessful();
        $this->assertDatabaseHas('comments', ['content' => 'Lovely', 'creator_id' => $chef->id]);
        $id = \App\Models\Comment::where('content', 'Lovely')->firstOrFail()->id;
        $this->putJson("/api/v1/comments/edit/{$id}", ['content' => 'Excellent'])->assertOk();
        $this->deleteJson("/api/v1/comments/delete/{$id}")->assertNoContent();
    }

    public function test_course_lists_redact_content_and_filter_by_search_and_expert(): void
    {
        $chef = $this->user();
        Course::create(['title' => 'Knife skills', 'description' => 'Chopping', 'content' => 'Private lesson', 'expert_id' => $chef->id]);
        Course::create(['title' => 'Baking', 'expert_id' => $this->user()->id]);
        foreach (['/courses?search=Chopping', '/courses?creator_id='.$chef->id, '/courses/list?search=Knife'] as $path) {
            $this->getJson('/api/v1'.$path)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Knife skills')->assertJsonMissingPath('data.0.content')->assertJsonMissingPath('data.0.video_url');
        }
    }

    public function test_course_video_lifecycle_and_access(): void
    {
        $chef = $this->user();
        Sanctum::actingAs($chef);
        $this->postJson('/api/v1/courses/create', [])->assertUnprocessable()->assertJsonValidationErrors('title');
        $response = $this->post('/api/v1/courses/create', ['title' => 'Knife', 'content' => 'Chop', 'video' => UploadedFile::fake()->create('lesson.mp4', 5, 'video/mp4')], ['Accept' => 'application/json'])->assertSuccessful();
        $id = $response->json('data.id');
        $oldPath = Storage::disk('public')->allFiles('course_videos')[0];
        $this->getJson("/api/v1/courses/{$id}")->assertOk()->assertJsonPath('data.content', 'Chop');
        $this->get("/api/v1/courses/{$id}/video")->assertOk()->assertHeader('Content-Disposition', 'inline');
        $this->put("/api/v1/courses/edit/{$id}", ['title' => 'Updated', 'content' => null, 'video' => UploadedFile::fake()->create('replacement.mp4', 5, 'video/mp4')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertCount(1, Storage::disk('public')->allFiles('course_videos'));
        $this->deleteJson("/api/v1/courses/delete/{$id}")->assertNoContent();
        $this->assertCount(0, Storage::disk('public')->allFiles('course_videos'));
    }

    public function test_missing_video_and_unentitled_course_access(): void
    {
        $chef = $this->user();
        $course = Course::create(['title' => 'Knife', 'expert_id' => $chef->id]);
        $this->getJson("/api/v1/courses/{$course->id}")->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/courses/{$course->id}")->assertForbidden();
        Sanctum::actingAs($chef);
        $this->getJson("/api/v1/courses/{$course->id}/video")->assertNotFound();
    }
}
