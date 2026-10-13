<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    public static function passwordStates(): array
    {
        return ['Google only' => [false], 'Google linked to a password account' => [true]];
    }

    #[DataProvider('passwordStates')]
    public function test_google_accounts_are_deleted_with_confirmation_and_without_a_password(bool $hasPassword): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-delete-test',
            'password' => $hasPassword ? Hash::make('password') : null,
            'avatar_url' => '/storage/user_avatars/google.jpg',
        ]);
        $user->assignRole('regular_user');
        Storage::disk('public')->put('user_avatars/google.jpg', 'avatar');
        $recipe = Recipe::create(['title' => 'Soup', 'description' => 'Warm', 'instructions' => 'Boil', 'creator_id' => $user->id]);
        $this->withToken($user->createToken('session')->plainTextToken);
        $user->createToken('other session');

        $this->getJson('/api/v1/users/me')->assertOk()
            ->assertJsonPath('data.has_google_account', true)
            ->assertJsonPath('data.has_password', $hasPassword)
            ->assertJsonMissingPath('data.google_id');
        $this->deleteJson('/api/v1/users/delete/'.$user->id)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->deleteJson('/api/v1/users/delete/'.$user->id, ['confirmed' => false])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->deleteJson('/api/v1/users/delete/'.$user->id, ['confirmed' => true])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseEmpty('personal_access_tokens');
        Storage::disk('public')->assertMissing('user_avatars/google.jpg');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/users/me')->assertUnauthorized();
    }

    public function test_confirmation_cannot_bypass_authentication_or_ownership(): void
    {
        $owner = User::factory()->create(['google_id' => 'google-owner', 'password' => null]);
        $this->deleteJson('/api/v1/users/delete/'.$owner->id, ['confirmed' => true])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson('/api/v1/users/delete/'.$owner->id, ['confirmed' => true])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->getJson('/api/v1/users/'.$owner->id)->assertOk()->assertJsonMissingPath('data.has_google_account');
    }

    public function test_password_accounts_cannot_use_confirmation_or_a_forged_google_flag_to_skip_the_password_check(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/users/me')->assertOk()->assertJsonPath('data.has_google_account', false);
        $this->deleteJson('/api/v1/users/delete/'.$user->id, ['confirmed' => true, 'has_google_account' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->deleteJson('/api/v1/users/delete/'.$user->id, ['confirmed' => true, 'password' => 'wrong'])->assertUnauthorized();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->deleteJson('/api/v1/users/delete/'.$user->id, ['password' => 'password'])->assertNoContent();
    }
}
