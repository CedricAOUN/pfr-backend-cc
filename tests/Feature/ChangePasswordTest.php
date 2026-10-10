<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD = 'Current!Password123';
    private const NEW_PASSWORD = 'New!Password456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->mock(UncompromisedVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andReturn(true);
        });
    }

    public function test_anonymous_requests_cannot_change_a_password(): void
    {
        $this->putJson('/api/v1/users/password', $this->payload())->assertUnauthorized();
    }

    public function test_password_change_updates_only_the_authenticated_user_and_keeps_the_session(): void
    {
        $user = User::factory()->create(['password' => self::CURRENT_PASSWORD]);
        $other = User::factory()->create(['password' => self::CURRENT_PASSWORD]);
        $token = $user->createToken('session');
        $this->withToken($token->plainTextToken);

        $this->putJson('/api/v1/users/password', [
            ...$this->payload(),
            'user_id' => $other->id,
            'email' => 'changed@example.com',
        ])->assertOk()->assertExactJson(['message' => 'Password changed successfully.']);

        $this->assertNotSame(self::NEW_PASSWORD, $user->fresh()->password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $other->fresh()->password));
        $this->assertSame($user->email, $user->fresh()->email);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/users/me')->assertOk()->assertJsonPath('data.has_password', true);
        $this->postJson('/api/v1/users/login', [
            'email' => $user->email, 'password' => self::CURRENT_PASSWORD,
        ])->assertUnauthorized();
        $this->postJson('/api/v1/users/login', [
            'email' => $user->email, 'password' => self::NEW_PASSWORD,
        ])->assertOk()->assertJsonStructure(['access_token']);
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_credentials_do_not_change_the_password(array $overrides, string $field): void
    {
        $user = User::factory()->create(['password' => self::CURRENT_PASSWORD]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/users/password', array_replace($this->payload(), $overrides))
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->password));
    }

    public static function invalidPasswords(): array
    {
        return [
            'missing current password' => [['current_password' => null], 'current_password'],
            'incorrect current password' => [['current_password' => 'incorrect'], 'current_password'],
            'missing new password' => [['password' => null], 'password'],
            'missing confirmation' => [['password_confirmation' => null], 'password'],
            'mismatched confirmation' => [['password_confirmation' => 'Different!Password123'], 'password'],
            'too short' => [['password' => 'Short!123', 'password_confirmation' => 'Short!123'], 'password'],
            'no uppercase' => [['password' => 'new!password456', 'password_confirmation' => 'new!password456'], 'password'],
            'no lowercase' => [['password' => 'NEW!PASSWORD456', 'password_confirmation' => 'NEW!PASSWORD456'], 'password'],
            'no number' => [['password' => 'New!PasswordABC', 'password_confirmation' => 'New!PasswordABC'], 'password'],
            'no symbol' => [['password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456'], 'password'],
            'unchanged password' => [[
                'password' => self::CURRENT_PASSWORD,
                'password_confirmation' => self::CURRENT_PASSWORD,
            ], 'password'],
        ];
    }

    public function test_compromised_passwords_are_rejected(): void
    {
        $user = User::factory()->create(['password' => self::CURRENT_PASSWORD]);
        Sanctum::actingAs($user);
        $this->mock(UncompromisedVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->once()->andReturn(false);
        });

        $this->putJson('/api/v1/users/password', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->password));
    }

    public function test_google_only_accounts_cannot_bypass_current_password_verification(): void
    {
        $user = User::factory()->create(['google_id' => 'google-only', 'password' => null]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/users/me')->assertOk()->assertJsonPath('data.has_password', false);

        $this->putJson('/api/v1/users/password', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertNull($user->fresh()->password);
    }

    private function payload(): array
    {
        return [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }
}
