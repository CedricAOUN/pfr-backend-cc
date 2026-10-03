<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
    }

    public function test_google_login_creates_a_user_and_returns_a_sanctum_token(): void
    {
        $this->googlePayload([
            'sub' => 'google-123',
            'email' => 'Julia.Child@example.com',
            'email_verified' => true,
            'given_name' => 'Julia',
            'family_name' => 'Child',
            'picture' => 'https://example.com/avatar.jpg',
        ]);

        $response = $this->postJson('/api/v1/users/google', ['credential' => 'valid-token']);

        $response->assertOk()
            ->assertJsonPath('user.name', 'julia.child')
            ->assertJsonPath('user.first_name', 'Julia')
            ->assertJsonPath('user.last_name', 'Child')
            ->assertJsonPath('user.avatar_url', 'https://example.com/avatar.jpg')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token']);

        $user = User::where('google_id', 'google-123')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('regular_user'));
    }

    public function test_google_login_uses_profile_fallbacks_and_suffixes_username_collisions(): void
    {
        User::factory()->create(['name' => 'julia.child']);
        $this->googlePayload([
            'sub' => 'google-456',
            'email' => 'julia.child@example.com',
            'email_verified' => true,
            'name' => 'Julia Child',
        ]);

        $this->postJson('/api/v1/users/google', ['credential' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('user.name', 'julia.child.2')
            ->assertJsonPath('user.first_name', null)
            ->assertJsonPath('user.last_name', null)
            ->assertJsonPath('user.avatar_url', null);
    }

    public function test_invalid_google_credentials_are_rejected_without_creating_a_user(): void
    {
        $this->googlePayload(null);

        $this->postJson('/api/v1/users/google', ['credential' => 'invalid-token'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid Google credential');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_unverified_google_email_is_rejected(): void
    {
        $this->googlePayload([
            'sub' => 'google-123',
            'email' => 'julia@example.com',
            'email_verified' => false,
        ]);

        $this->postJson('/api/v1/users/google', ['credential' => 'valid-token'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_returning_google_user_is_reused_without_overwriting_the_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'local-name',
            'email' => 'julia@example.com',
            'google_id' => 'google-123',
            'first_name' => 'Locally edited',
            'avatar_url' => 'https://example.com/local.jpg',
        ]);
        $this->googlePayload([
            'sub' => 'google-123',
            'email' => 'changed@example.com',
            'email_verified' => true,
            'given_name' => 'Google',
            'picture' => 'https://example.com/google.jpg',
        ]);

        $this->postJson('/api/v1/users/google', ['credential' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.first_name', 'Locally edited')
            ->assertJsonPath('user.avatar_url', 'https://example.com/local.jpg');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_manual_account_requires_and_verifies_password_before_linking(): void
    {
        $user = User::factory()->create([
            'name' => 'existing-user',
            'email' => 'julia@example.com',
            'password' => Hash::make('Password123!xyz'),
        ]);
        $payload = [
            'sub' => 'google-123',
            'email' => 'julia@example.com',
            'email_verified' => true,
        ];
        $this->googlePayload($payload, 3);

        $this->postJson('/api/v1/users/google', ['credential' => 'valid-token'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'account_link_required');

        $this->postJson('/api/v1/users/google', [
            'credential' => 'valid-token',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('code', 'invalid_link_password');

        $this->postJson('/api/v1/users/google', [
            'credential' => 'valid-token',
            'password' => 'Password123!xyz',
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'google_id' => 'google-123']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_manual_registration_requires_unique_username_and_returns_token(): void
    {
        $this->mock(UncompromisedVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andReturn(true);
        });

        User::factory()->create(['name' => 'julia']);

        $this->postJson('/api/v1/users/register', [
            'name' => 'julia',
            'email' => 'another@example.com',
            'password' => 'Password123!xyz',
            'password_confirmation' => 'Password123!xyz',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/users/register', [
            'name' => 'marco',
            'email' => 'marco@example.com',
            'password' => 'Password123!xyz',
            'password_confirmation' => 'Password123!xyz',
        ])->assertOk()->assertJsonStructure(['user', 'access_token', 'token_type']);
    }

    public function test_password_login_rejects_google_only_account_cleanly(): void
    {
        User::factory()->create([
            'name' => 'google-user',
            'email' => 'google@example.com',
            'password' => null,
            'google_id' => 'google-123',
        ]);

        $this->postJson('/api/v1/users/login', [
            'email' => 'google@example.com',
            'password' => 'Password',
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_manual_registration_returns_a_usable_token_when_welcome_email_fails(): void
    {
        Exceptions::fake();
        $failure = new \RuntimeException('Welcome email delivery failed');
        Mail::shouldReceive('raw')->once()->andThrow($failure);
        $this->mock(UncompromisedVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $response = $this->postJson('/api/v1/users/register', [
            'name' => 'marco',
            'email' => 'marco@example.com',
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ]);

        $response->assertOk()->assertJsonPath('token_type', 'Bearer');
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(User::where('email', 'marco@example.com')->firstOrFail()->hasRole('regular_user'));
        $this->withToken($response->json('access_token'))->getJson('/api/v1/users/me')
            ->assertOk()->assertJsonPath('data.email', 'marco@example.com');
        Exceptions::assertReported(fn(\RuntimeException $exception) => $exception === $failure);
    }

    public function test_google_registration_returns_a_usable_token_when_welcome_email_fails(): void
    {
        Exceptions::fake();
        $failure = new \RuntimeException('Welcome email delivery failed');
        Mail::shouldReceive('raw')->once()->andThrow($failure);
        $this->googlePayload([
            'sub' => 'google-mail-failure',
            'email' => 'julia@example.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/api/v1/users/google', ['credential' => 'valid-token']);

        $response->assertOk()->assertJsonPath('token_type', 'Bearer');
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(User::where('google_id', 'google-mail-failure')->firstOrFail()->hasRole('regular_user'));
        $this->withToken($response->json('access_token'))->getJson('/api/v1/users/me')
            ->assertOk()->assertJsonPath('data.email', 'julia@example.com');
        Exceptions::assertReported(fn(\RuntimeException $exception) => $exception === $failure);
    }

    private function googlePayload(?array $payload, int $times = 1): void
    {
        $this->mock(GoogleIdTokenVerifier::class, function (MockInterface $mock) use ($payload, $times) {
            $mock->shouldReceive('verify')->times($times)->andReturn($payload);
        });
    }
}
