<?php

namespace Tests\Feature;

use App\Mail\SubscriptionCancelled;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Subscription;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SubscriptionCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['cashier.secret' => 'sk_test_dummy']);
        Mail::fake();
        $transport = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $transport->shouldNotReceive('request');
        \Stripe\ApiRequestor::setHttpClient($transport);
    }

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());
        parent::tearDown();
    }

    private function subscriber(string $id = 'test'): User
    {
        $user = User::factory()->create(['stripe_id' => 'cus_'.$id]);
        $user->assignRole('premium_user');
        $subscription = $user->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_'.$id, 'stripe_status' => 'active',
            'stripe_price' => 'price_test', 'quantity' => 1,
            'current_period_end' => now()->addMonth()->startOfDay(),
        ]);
        $subscription->items()->create([
            'stripe_id' => 'si_'.$id, 'stripe_product' => 'prod_premium', 'stripe_price' => 'price_test', 'quantity' => 1,
        ]);

        return $user;
    }

    private function expectStripeCancellation(Subscription $subscription): void
    {
        $transport = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $transport->shouldReceive('request')->once()
            ->withArgs(fn ($method, $url, $headers, $params) => $method === 'post'
                && $url === 'https://api.stripe.com/v1/subscriptions/'.$subscription->stripe_id
                && $params === ['cancel_at_period_end' => 'true'])
            ->andReturn([json_encode(['id' => $subscription->stripe_id, 'object' => 'subscription', 'status' => 'active', 'cancel_at_period_end' => true]), 200, []]);
        $transport->shouldReceive('request')->once()
            ->withArgs(fn ($method, $url) => $method === 'get' && $url === 'https://api.stripe.com/v1/subscription_items/si_test')
            ->andReturn([json_encode(['id' => 'si_test', 'object' => 'subscription_item', 'current_period_end' => Carbon::parse($subscription->current_period_end)->timestamp]), 200, []]);
        \Stripe\ApiRequestor::setHttpClient($transport);
    }

    public function test_cancellation_stops_renewal_keeps_access_and_sends_one_email_even_on_repeat_requests(): void
    {
        $user = $this->subscriber();
        $other = $this->subscriber('other');
        $subscription = $user->subscription('default');
        $this->expectStripeCancellation($subscription);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscription/cancel', ['user_id' => $other->id, 'subscription_id' => 'sub_other'])
            ->assertOk()->assertJsonPath('email_sent', true);
        $this->postJson('/api/v1/subscription/cancel')->assertOk()->assertJsonPath('email_sent', true);

        $cancelled = $subscription->fresh();
        $this->assertTrue($cancelled->onGracePeriod());
        $this->assertEquals(Carbon::parse($subscription->current_period_end), $cancelled->ends_at);
        $this->assertNotNull($cancelled->cancellation_email_sent_at);
        $this->assertTrue($user->fresh()->hasRole('premium_user'));
        $this->assertNull($other->fresh()->subscription('default')->ends_at);
        Mail::assertSent(SubscriptionCancelled::class, function ($mail) use ($user, $cancelled) {
            return $mail->hasTo($user->email) && $mail->name === $user->name
                && $mail->endsAt->equalTo($cancelled->ends_at)
                && str_contains($mail->render(), 'will not renew');
        });
        Mail::assertSentCount(1);
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/users/me')->assertOk()
            ->assertJsonPath('data.is_premium', true)
            ->assertJsonPath('data.subscription.cancelled', true)
            ->assertJsonPath('data.subscription.can_cancel', false)
            ->assertJsonPath('data.subscription.cancellation_email_sent', true);
        $this->getJson('/api/v1/users/'.$other->id)->assertOk()->assertJsonMissingPath('data.subscription');
    }

    public function test_anonymous_and_non_subscribers_cannot_cancel_another_users_plan(): void
    {
        $other = $this->subscriber('other');
        $this->postJson('/api/v1/subscription/cancel')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/subscription/cancel', ['user_id' => $other->id])->assertStatus(409);
        $this->assertNull($other->fresh()->subscription('default')->ends_at);
        Mail::assertNothingSent();
    }

    public function test_ended_subscriptions_cannot_be_cancelled_again(): void
    {
        $user = $this->subscriber();
        $user->subscription('default')->forceFill(['stripe_status' => 'canceled', 'ends_at' => now()->subDay()])->save();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/subscription/cancel')->assertStatus(409);
        Mail::assertNothingSent();
    }

    public function test_stripe_failure_does_not_mark_the_subscription_cancelled_or_send_an_email(): void
    {
        $user = $this->subscriber();
        $transport = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $transport->shouldReceive('request')->once()->andThrow(new \RuntimeException('Stripe unavailable'));
        \Stripe\ApiRequestor::setHttpClient($transport);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/subscription/cancel')->assertStatus(500);
        $this->assertNull($user->fresh()->subscription('default')->ends_at);
        Mail::assertNothingSent();
    }

    public function test_email_failure_preserves_cancellation_and_retry_sends_the_email_without_calling_stripe_again(): void
    {
        $user = $this->subscriber();
        $subscription = $user->subscription('default');
        $this->expectStripeCancellation($subscription);
        $mailManager = Mail::getFacadeRoot()->manager;
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/subscription/cancel')->assertOk()->assertJsonPath('email_sent', false);
        $this->assertTrue($subscription->fresh()->onGracePeriod());
        $this->assertNull($subscription->fresh()->cancellation_email_sent_at);

        Mail::swap($mailManager);
        Mail::fake();
        $this->postJson('/api/v1/subscription/cancel')->assertOk()->assertJsonPath('email_sent', true);
        Mail::assertSent(SubscriptionCancelled::class, fn ($mail) => $mail->hasTo($user->email));
        Mail::assertSentCount(1);
    }
}
