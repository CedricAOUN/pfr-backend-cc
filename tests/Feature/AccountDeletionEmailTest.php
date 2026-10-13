<?php

namespace Tests\Feature;

use App\Mail\AccountDeleted;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountDeletionEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        Mail::fake();
    }

    public static function accountTypes(): array
    {
        return ['password account' => [false], 'Google account' => [true]];
    }

    #[DataProvider('accountTypes')]
    public function test_successful_deletion_sends_a_confirmation_to_the_deleted_account(bool $google): void
    {
        $user = User::factory()->create($google ? ['google_id' => 'google-delete', 'password' => null] : []);
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/users/delete/'.$user->id, $google ? ['confirmed' => true] : ['password' => 'password'])
            ->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Mail::assertSent(AccountDeleted::class, fn ($mail) => $mail->hasTo($user->email)
            && $mail->name === $user->name && str_contains($mail->render(), 'account has been deleted'));
        Mail::assertSentCount(1);
    }

    public function test_invalid_password_or_missing_confirmation_does_not_send_a_deletion_email(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/users/delete/'.$user->id, ['password' => 'wrong'])->assertUnauthorized();
        $google = User::factory()->create(['google_id' => 'google-delete', 'password' => null]);
        Sanctum::actingAs($google);
        $this->deleteJson('/api/v1/users/delete/'.$google->id, ['confirmed' => false])->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_failed_deletion_does_not_send_a_confirmation_email(): void
    {
        $user = User::factory()->create(['avatar_url' => '/storage/user_avatars/delete.jpg']);
        $disk = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with('user_avatars/delete.jpg')->andReturn(true);
        $disk->shouldReceive('delete')->once()->with('user_avatars/delete.jpg')->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/users/delete/'.$user->id, ['password' => 'password'])->assertStatus(500);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        Mail::assertNothingSent();
    }

    public function test_email_failure_is_logged_without_undoing_account_deletion(): void
    {
        config(['logging.channels.integrations.handler_with.stream' => 'php://memory']);
        Log::forgetChannel('integrations');
        $user = User::factory()->create();
        $user->createToken('session');
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Email provider unavailable', 503));
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/users/delete/'.$user->id, ['password' => 'password'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseEmpty('personal_access_tokens');

        $stream = Log::channel('integrations')->getLogger()->getHandlers()[0]->getStream();
        rewind($stream);
        $started = json_decode(fgets($stream), true, flags: JSON_THROW_ON_ERROR);
        $failed = json_decode(fgets($stream), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('mail.send.failed', $failed['message']);
        $this->assertSame('account_deleted', $failed['context']['notification']);
        $this->assertSame('Email provider unavailable', $failed['context']['error_message']);
        $this->assertSame($started['context']['operation_id'], $failed['context']['operation_id']);
    }
}
