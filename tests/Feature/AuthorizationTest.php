<?php

namespace Tests\Feature;

use App\Http\Controllers\API\CheckoutController;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_owner_keeps_recipe_update_and_delete_rights_after_downgrade(): void
    {
        $owner = $this->userWithRole('regular_user');
        $recipe = $this->recipe($owner, true);

        Sanctum::actingAs($owner, [], 'sanctum');

        $this->putJson("/api/v1/recipes/edit/{$recipe->id}", [
            'title' => 'Updated by owner',
            'is_premium' => true,
        ])->assertOk();

        $this->deleteJson("/api/v1/recipes/delete/{$recipe->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
    }

    public function test_non_owner_cannot_modify_content_but_admin_can(): void
    {
        $owner = $this->userWithRole('regular_user');
        $otherChef = $this->userWithRole('chef');
        $recipe = $this->recipe($owner);
        $course = Course::create([
            'title' => 'Owner course',
            'description' => 'Description',
            'expert_id' => $owner->id,
        ]);

        Sanctum::actingAs($otherChef, [], 'sanctum');
        $this->putJson("/api/v1/recipes/edit/{$recipe->id}", ['title' => 'Stolen'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/courses/delete/{$course->id}")
            ->assertForbidden();

        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin, [], 'sanctum');
        $this->putJson("/api/v1/recipes/edit/{$recipe->id}", ['title' => 'Moderated'])
            ->assertOk();
        $this->deleteJson("/api/v1/courses/delete/{$course->id}")
            ->assertNoContent();
    }

    public function test_user_can_delete_self_but_not_another_account_and_admin_can_delete_any_account(): void
    {
        $user = $this->userWithRole('regular_user');
        $target = $this->userWithRole('regular_user');

        Sanctum::actingAs($user, [], 'sanctum');
        $this->deleteJson("/api/v1/users/delete/{$target->id}")
            ->assertForbidden();
        $this->deleteJson("/api/v1/users/delete/{$user->id}")
            ->assertNoContent();

        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin, [], 'sanctum');
        $this->deleteJson("/api/v1/users/delete/{$target->id}")
            ->assertNoContent();
    }

    public function test_comment_owner_fields_cannot_be_reassigned(): void
    {
        $owner = $this->userWithRole('regular_user');
        $other = $this->userWithRole('regular_user');
        $recipe = $this->recipe($owner);
        $otherRecipe = $this->recipe($other);
        $comment = Comment::create([
            'content' => 'Original',
            'creator_id' => $owner->id,
            'recipe_id' => $recipe->id,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');
        $this->putJson("/api/v1/comments/edit/{$comment->id}", [
            'content' => 'Edited',
            'creator_id' => $other->id,
            'recipe_id' => $otherRecipe->id,
        ])->assertOk();

        $comment->refresh();
        $this->assertSame('Edited', $comment->content);
        $this->assertSame($owner->id, $comment->creator_id);
        $this->assertSame($recipe->id, $comment->recipe_id);
    }

    public function test_downgraded_owners_can_update_and_delete_their_courses_and_comments(): void
    {
        $owner = $this->userWithRole('regular_user');
        $recipe = $this->recipe($owner);
        $course = Course::create([
            'title' => 'Original course',
            'description' => 'Description',
            'expert_id' => $owner->id,
        ]);
        $comment = Comment::create([
            'content' => 'Original comment',
            'creator_id' => $owner->id,
            'recipe_id' => $recipe->id,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $this->putJson("/api/v1/courses/edit/{$course->id}", ['title' => 'Updated course'])
            ->assertOk();
        $this->deleteJson("/api/v1/comments/delete/{$comment->id}")
            ->assertNoContent();
        $this->deleteJson("/api/v1/courses/delete/{$course->id}")
            ->assertNoContent();
    }

    public function test_role_capabilities_match_the_product_tiers(): void
    {
        $regular = $this->userWithRole('regular_user');
        $premium = $this->userWithRole('premium_user');
        $chef = $this->userWithRole('chef');

        $this->assertTrue($regular->can('likes.update'));
        $this->assertTrue($regular->can('favorites.update'));
        $this->assertFalse($regular->can('recipes.create'));
        $this->assertTrue($premium->can('recipes.create'));
        $this->assertFalse($premium->can('premium-recipes.create'));
        $this->assertTrue($chef->can('premium-recipes.create'));
        $this->assertTrue($chef->can('courses.create'));
    }

    public function test_premium_user_can_create_free_recipe_and_regular_user_cannot_promote_one(): void
    {
        $premium = $this->userWithRole('premium_user');
        Sanctum::actingAs($premium, [], 'sanctum');

        $this->postJson('/api/v1/recipes/create', $this->recipePayload())
            ->assertSuccessful();

        $owner = $this->userWithRole('regular_user');
        $recipe = $this->recipe($owner);
        Sanctum::actingAs($owner, [], 'sanctum');

        $this->putJson("/api/v1/recipes/edit/{$recipe->id}", ['is_premium' => true])
            ->assertForbidden();
        $this->assertFalse($recipe->fresh()->is_premium);

        $this->postJson('/api/v1/recipes/create', $this->recipePayload())
            ->assertForbidden();

        $chef = $this->userWithRole('chef');
        Sanctum::actingAs($chef, [], 'sanctum');
        $premiumPayload = $this->recipePayload();
        $premiumPayload['is_premium'] = true;
        $this->postJson('/api/v1/recipes/create', $premiumPayload)
            ->assertSuccessful();

        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin, [], 'sanctum');
        $this->postJson('/api/v1/recipes/create', $premiumPayload)
            ->assertSuccessful();
    }

    public function test_regular_user_can_like_and_favorite_but_cannot_create_comments(): void
    {
        $regular = $this->userWithRole('regular_user');
        $recipe = $this->recipe($regular);
        Sanctum::actingAs($regular, [], 'sanctum');

        $this->postJson("/api/v1/recipes/{$recipe->id}/like")->assertOk();
        $this->postJson("/api/v1/recipes/{$recipe->id}/favorite")->assertOk();
        $this->postJson('/api/v1/comments/create', [
            'content' => 'Not premium',
            'recipe_id' => $recipe->id,
        ])->assertForbidden();
    }

    public function test_public_lists_omit_private_and_premium_detail_fields(): void
    {
        $owner = $this->userWithRole('chef');
        $recipe = $this->recipe($owner, true);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.premium_expire')
            ->assertJsonMissingPath('data.0.favorite_recipes');

        $this->getJson('/api/v1/recipes')
            ->assertOk()
            ->assertJsonPath('recipes.0.id', $recipe->id)
            ->assertJsonMissingPath('recipes.0.instructions')
            ->assertJsonMissingPath('recipes.0.ingredients')
            ->assertJsonMissingPath('recipes.0.comments');
    }

    public function test_premium_recipe_is_visible_to_owner_and_entitled_users_only(): void
    {
        $owner = $this->userWithRole('regular_user');
        $recipe = $this->recipe($owner, true);

        $this->getJson("/api/v1/recipes/{$recipe->id}")->assertForbidden();

        Sanctum::actingAs($owner, [], 'sanctum');
        $this->getJson("/api/v1/recipes/{$recipe->id}")->assertOk();

        $regular = $this->userWithRole('regular_user');
        Sanctum::actingAs($regular, [], 'sanctum');
        $this->getJson("/api/v1/recipes/{$recipe->id}")->assertForbidden();

        $premium = $this->userWithRole('premium_user');
        Sanctum::actingAs($premium, [], 'sanctum');
        $this->getJson("/api/v1/recipes/{$recipe->id}")->assertOk();
    }

    public function test_demo_role_seeding_is_complete_and_idempotent(): void
    {
        $demoUsers = [
            'admin@example.com' => 'admin',
            'martin@example.com' => 'chef',
            'sophie@example.com' => 'chef',
            'jean@example.com' => 'premium_user',
            'marie@example.com' => 'regular_user',
            'pierre@example.com' => 'regular_user',
            'claire@example.com' => 'regular_user',
        ];

        foreach (array_keys($demoUsers) as $email) {
            User::factory()->create(['email' => $email]);
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ($demoUsers as $email => $role) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertTrue($user->hasExactRoles($role));
        }
    }

    public function test_checkout_order_details_require_authentication_and_customer_ownership(): void
    {
        $this->getJson('/api/v1/stripe/order-details/cs_test')->assertUnauthorized();

        $user = $this->userWithRole('regular_user', ['stripe_id' => 'cus_owner']);
        Sanctum::actingAs($user, [], 'sanctum');

        $controller = new class extends CheckoutController
        {
            protected function retrieveCheckoutSession(string $sessionId): object
            {
                return (object) ['id' => $sessionId, 'customer' => 'cus_someone_else'];
            }
        };
        $this->app->instance(CheckoutController::class, $controller);

        $this->getJson('/api/v1/stripe/order-details/cs_test')->assertForbidden();
    }

    public function test_missing_owned_resource_returns_not_found(): void
    {
        Sanctum::actingAs($this->userWithRole('regular_user'), [], 'sanctum');

        $this->deleteJson('/api/v1/recipes/delete/999999')->assertNotFound();
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function recipe(User $owner, bool $premium = false): Recipe
    {
        return Recipe::create([
            'title' => 'Recipe title',
            'description' => 'Recipe description',
            'instructions' => 'Recipe instructions',
            'is_premium' => $premium,
            'creator_id' => $owner->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function recipePayload(): array
    {
        return [
            'title' => 'Created recipe',
            'description' => 'Description',
            'instructions' => 'Instructions',
            'is_premium' => false,
            'ingredients' => [
                ['name' => 'Salt', 'quantity' => 1, 'unit' => 'tsp'],
            ],
        ];
    }
}
