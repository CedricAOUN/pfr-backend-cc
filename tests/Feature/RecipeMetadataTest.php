<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecipeMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->assignRole('premium_user');
        Sanctum::actingAs($user);
        return $user;
    }

    private function payload(): array
    {
        return ['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'category' => 'starter', 'duration' => 30,
            'ingredients' => [['name' => 'Carrot', 'quantity' => 2, 'unit' => 'pcs']]];
    }

    public function test_category_and_duration_are_required_on_creation_and_persist_on_edit(): void
    {
        $this->owner();
        $payload = $this->payload();
        unset($payload['category'], $payload['duration']);
        $this->postJson('/api/v1/recipes/create', $payload)->assertUnprocessable()->assertJsonValidationErrors(['category', 'duration']);
        $created = $this->postJson('/api/v1/recipes/create', $this->payload())->assertSuccessful()
            ->assertJsonPath('data.category', 'starter')->assertJsonPath('data.duration', 30);
        $id = $created->json('data.id');
        $this->putJson('/api/v1/recipes/edit/'.$id, ['category' => 'main', 'duration' => 45])->assertOk()
            ->assertJsonPath('data.category', 'main')->assertJsonPath('data.duration', 45);
        $this->assertDatabaseHas('recipes', ['id' => $id, 'category' => 'main', 'duration' => 45]);
        $this->getJson('/api/v1/recipes/'.$id)->assertOk()->assertJsonPath('data.duration', 45);
    }

    public function test_form_post_method_override_updates_recipe_metadata(): void
    {
        $owner = $this->owner();
        $recipe = Recipe::create([...$this->payload(), 'creator_id' => $owner->id]);
        $this->post('/api/v1/recipes/edit/'.$recipe->id, [
            '_method' => 'PUT', 'category' => 'dessert', 'duration' => '25',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.category', 'dessert')->assertJsonPath('data.duration', 25);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'category' => 'dessert', 'duration' => 25]);
    }

    public static function invalidMetadata(): array
    {
        return [['category', 'unknown'], ['category', ''], ['duration', 0], ['duration', -1], ['duration', 1.5], ['duration', 'abc'], ['duration', null], ['duration', 4294967296]];
    }

    #[DataProvider('invalidMetadata')]
    public function test_invalid_category_or_duration_is_rejected_on_creation_and_edit(string $field, mixed $value): void
    {
        $owner = $this->owner();
        $this->postJson('/api/v1/recipes/create', [...$this->payload(), $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        $recipe = Recipe::create([...$this->payload(), 'creator_id' => $owner->id]);
        $this->putJson('/api/v1/recipes/edit/'.$recipe->id, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'category' => 'starter', 'duration' => 30]);
    }

    public function test_category_filter_combines_with_search_and_legacy_recipes_keep_unknown_metadata(): void
    {
        $owner = $this->owner();
        $main = Recipe::create([...$this->payload(), 'creator_id' => $owner->id, 'category' => 'main']);
        Recipe::create([...$this->payload(), 'creator_id' => $owner->id, 'title' => 'Cake', 'category' => 'dessert']);
        $legacy = Recipe::create(['title' => 'Legacy soup', 'description' => 'Warm', 'instructions' => 'Boil', 'creator_id' => $owner->id]);
        $this->assertNull($legacy->fresh()->duration);
        $this->assertNull($legacy->fresh()->category);
        $this->getJson('/api/v1/recipes?category=main&search=Soup')->assertOk()->assertJsonCount(1, 'recipes')
            ->assertJsonPath('recipes.0.id', $main->id)->assertJsonPath('recipes.0.category', 'main')->assertJsonPath('recipes.0.duration', 30);
        $this->getJson('/api/v1/recipes?category=dessert&search=Soup')->assertOk()->assertJsonCount(0, 'recipes');
        $this->getJson('/api/v1/recipes')->assertOk()->assertJsonCount(3, 'recipes');
        $this->getJson('/api/v1/recipes?category=unknown')->assertUnprocessable();
    }
}
