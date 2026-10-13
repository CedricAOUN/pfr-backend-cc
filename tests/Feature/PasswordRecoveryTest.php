<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLink;
use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Mockery\MockInterface;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->mock(UncompromisedVerifier::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->andReturn(true));
        config(['app.frontend_url' => 'https://mealmosaic.example']);
    }

    private function resetPayload(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'Strong!Password456', 'password_confirmation' => 'Strong!Password456'];
    }

    public function test_recovery_sends_a_hashed_expiring_token_and_reset_is_single_use_and_revokes_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('session');
        DB::table('sessions')->insert(['id' => 'session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->postJson('/api/v1/users/forgot-password', ['email' => $user->email])->assertOk();
        $token = null;
        Mail::assertSent(PasswordResetLink::class, function ($mail) use ($user, &$token) {
            parse_str(parse_url($mail->url, PHP_URL_QUERY), $query);
            $token = $query['token'];
            $this->assertSame($user->email, $query['email']);
            $this->assertSame(60, $mail->expiresIn);
            $this->assertStringContainsString('https://mealmosaic.example/reset-password?', $mail->url);
            $this->assertStringContainsString($mail->url, $mail->render());
            return $mail->hasTo($user->email);
        });
        $this->assertTrue(Hash::check($token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token')));
        $this->postJson('/api/v1/users/forgot-password', ['email' => $user->email])->assertOk();
        Mail::assertSentCount(1);
        $this->postJson('/api/v1/users/reset-password', $this->resetPayload($user, $token))->assertOk();
        $this->assertTrue(Hash::check('Strong!Password456', $user->fresh()->password));
        $this->assertDatabaseEmpty('personal_access_tokens');
        $this->assertDatabaseEmpty('sessions');
        $this->assertDatabaseEmpty('password_reset_tokens');
        $this->postJson('/api/v1/users/reset-password', $this->resetPayload($user, $token))->assertUnprocessable();
    }

    public function test_google_and_unknown_accounts_get_the_same_response_and_no_reset_email(): void
    {
        $google = User::factory()->create(['google_id' => 'google-only', 'password' => null]);
        $linked = User::factory()->create(['google_id' => 'google-linked']);
        $responses = [];
        foreach ([$google->email, $linked->email, 'unknown@example.com'] as $email) {
            $responses[] = $this->postJson('/api/v1/users/forgot-password', ['email' => $email])->assertOk()->json();
        }
        $this->assertSame($responses[0], $responses[1]);
        $this->assertSame($responses[0], $responses[2]);
        Mail::assertNothingSent();
        $this->assertDatabaseEmpty('password_reset_tokens');
        $token = Password::createToken($linked);
        $this->postJson('/api/v1/users/reset-password', $this->resetPayload($linked, $token))->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $linked->fresh()->password));
    }

    public function test_expired_wrong_and_mismatched_tokens_and_weak_passwords_are_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = Password::createToken($user);
        $this->postJson('/api/v1/users/reset-password', $this->resetPayload($user, 'wrong'))->assertUnprocessable();
        $this->postJson('/api/v1/users/reset-password', $this->resetPayload($other, $token))->assertUnprocessable();
        $this->postJson('/api/v1/users/reset-password', [...$this->resetPayload($user, $token), 'password' => 'weak'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/users/reset-password', [...$this->resetPayload($user, $token), 'password_confirmation' => 'different'])->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->postJson('/api/v1/users/reset-password', $this->resetPayload($user, $token))->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_invalid_email_is_rejected_and_public_requests_are_rate_limited(): void
    {
        $this->postJson('/api/v1/users/forgot-password', ['email' => 'invalid'])->assertUnprocessable();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/users/forgot-password', ['email' => 'unknown@example.com'])->assertOk();
        }
        $this->postJson('/api/v1/users/forgot-password', ['email' => 'unknown@example.com'])->assertStatus(429);
    }

    public function test_failed_email_does_not_leave_a_reset_token_or_disclose_account_existence(): void
    {
        $user = User::factory()->create();
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $response = $this->postJson('/api/v1/users/forgot-password', ['email' => $user->email])->assertOk()->json();
        $unknown = $this->postJson('/api/v1/users/forgot-password', ['email' => 'unknown@example.com'])->assertOk()->json();
        $this->assertSame($unknown, $response);
        $this->assertDatabaseEmpty('password_reset_tokens');
    }
}
