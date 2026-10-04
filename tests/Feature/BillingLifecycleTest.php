<?php

namespace Tests\Feature;

use App\Http\Controllers\API\CheckoutController;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class BillingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['cashier.webhook.secret' => null, 'plans.premium.product' => 'prod_premium', 'plans.chef.product' => 'prod_chef', 'plans.premium.monthly' => 'price_premium']);
        Mail::fake();
        // Fail immediately if a test accidentally reaches Stripe's transport.
        $transport = Mockery::mock(\Stripe\HttpClient\ClientInterface::class);
        $transport->shouldNotReceive('request');
        \Stripe\ApiRequestor::setHttpClient($transport);
    }

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());
        parent::tearDown();
    }

    private function customer(): User
    {
        $user = User::factory()->create(['stripe_id' => 'cus_test']);
        $user->assignRole('regular_user');
        return $user;
    }

    private function event(string $type, string $product = 'prod_premium', string $status = 'active', string $id = 'sub_test'): array
    {
        return ['id' => 'evt_test', 'type' => 'customer.subscription.'.$type, 'data' => ['object' => [
            'id' => $id, 'customer' => 'cus_test', 'status' => $status, 'metadata' => ['type' => 'default'],
            'items' => ['data' => [['id' => 'si_'.$id, 'quantity' => 1, 'current_period_end' => 1900000000, 'price' => ['id' => 'price_test', 'product' => $product]]]],
        ]]];
    }

    public function test_webhook_creation_replay_and_tier_switch_update_roles_and_period_end(): void
    {
        $user = $this->customer();
        foreach (range(1, 2) as $_) {
            $this->postJson('/api/v1/stripe/webhook', $this->event('created'))->assertOk();
        }
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_items', 1);
        $this->assertTrue($user->fresh()->hasRole('premium_user'));
        $this->assertSame(1900000000, \Carbon\Carbon::parse($user->fresh()->subscription('default')->current_period_end)->timestamp);
        $this->postJson('/api/v1/stripe/webhook', $this->event('updated', 'prod_chef'))->assertOk();
        $this->assertTrue($user->fresh()->hasRole('chef'));
        $this->assertFalse($user->fresh()->hasRole('premium_user'));
        $this->postJson('/api/v1/stripe/webhook', $this->event('updated', 'prod_premium'))->assertOk();
        $this->assertTrue($user->fresh()->hasRole('premium_user'));
        $this->assertFalse($user->fresh()->hasRole('chef'));
    }

    public function test_inactive_subscriptions_do_not_grant_roles_and_unknown_customers_are_acknowledged(): void
    {
        $user = $this->customer();
        $this->postJson('/api/v1/stripe/webhook', $this->event('created', 'prod_chef', 'incomplete'))->assertOk();
        $this->assertFalse($user->fresh()->hasRole('chef'));
        $event = $this->event('updated');
        $event['data']['object']['customer'] = 'cus_absent';
        foreach (['created', 'updated', 'deleted'] as $type) {
            $event['type'] = 'customer.subscription.'.$type;
            $this->postJson('/api/v1/stripe/webhook', $event)->assertOk();
        }
    }

    public function test_subscription_deletion_preserves_other_active_plan_then_downgrades_last_plan(): void
    {
        $user = $this->customer();
        $this->postJson('/api/v1/stripe/webhook', $this->event('created'))->assertOk();
        $this->postJson('/api/v1/stripe/webhook', $this->event('created', 'prod_premium', 'active', 'sub_second'))->assertOk();
        $this->postJson('/api/v1/stripe/webhook', $this->event('deleted'))->assertOk();
        $this->assertTrue($user->fresh()->hasRole('premium_user'));
        $this->postJson('/api/v1/stripe/webhook', $this->event('deleted', 'prod_premium', 'canceled', 'sub_second'))->assertOk();
        $this->assertFalse($user->fresh()->hasRole('premium_user'));
        $this->assertTrue($user->fresh()->hasRole('regular_user'));
    }

    public function test_mail_failure_does_not_undo_subscription_activation(): void
    {
        $user = $this->customer();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $this->postJson('/api/v1/stripe/webhook', $this->event('created'))->assertOk();
        $this->assertTrue($user->fresh()->hasRole('premium_user'));
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_checkout_validation_and_existing_subscription_conflict(): void
    {
        $user = $this->customer();
        $this->postJson('/api/v1/checkout', [])->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/checkout', ['product' => 'unknown', 'interval' => 'weekly'])->assertUnprocessable()->assertJsonValidationErrors(['product', 'interval']);
        config(['plans.chef.annual' => null]);
        $this->postJson('/api/v1/checkout', ['product' => 'chef', 'interval' => 'annual'])->assertUnprocessable();
        $user->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_active', 'stripe_status' => 'active', 'stripe_price' => 'price_test', 'quantity' => 1]);
        $user->unsetRelation('subscriptions');
        $this->postJson('/api/v1/checkout', ['product' => 'premium', 'interval' => 'monthly'])->assertStatus(409);
    }

    public function test_owned_order_details_are_returned_without_network_access(): void
    {
        Sanctum::actingAs($this->customer());
        $this->app->instance(CheckoutController::class, new class extends CheckoutController {
            protected function retrieveCheckoutSession(string $sessionId): object
            {
                return (object) ['id' => $sessionId, 'customer' => (object) ['id' => 'cus_test'], 'amount_total' => 1200];
            }
        });
        $this->getJson('/api/v1/stripe/order-details/cs_test')->assertOk()->assertJsonPath('amount_total', 1200);
    }

    public function test_checkout_uses_configured_price_and_frontend_return_urls(): void
    {
        $customer = $this->customer();
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes($customer->getAttributes(), true);
        $user->exists = true;
        $user->shouldReceive('subscribed')->with('default')->andReturn(false);
        config(['app.frontend_url' => 'https://meal.example']);
        foreach (['premium', 'chef'] as $product) {
            foreach (['monthly', '6_months', 'annual'] as $interval) {
                $price = "price_{$product}_{$interval}";
                config(["plans.{$product}.{$interval}" => $price]);
                $builder = Mockery::mock(\Laravel\Cashier\SubscriptionBuilder::class);
                $builder->shouldReceive('checkout')->once()->with([
                    'success_url' => 'https://meal.example/billing/success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => 'https://meal.example/billing/cancel',
                ])->andReturn((object) ['url' => 'https://checkout.stripe.test/session']);
                $user->shouldReceive('newSubscription')->once()->with('default', $price)->andReturn($builder);
            }
        }
        Sanctum::actingAs($user);
        foreach (['premium', 'chef'] as $product) {
            foreach (['monthly', '6_months', 'annual'] as $interval) {
                $this->postJson('/api/v1/checkout', compact('product', 'interval'))->assertOk()->assertJsonPath('checkout_url', 'https://checkout.stripe.test/session');
            }
        }
    }

    public function test_plan_details_accept_only_configured_prices(): void
    {
        $client = Mockery::mock(StripeClient::class);
        $plans = Mockery::mock(\Stripe\Service\PlanService::class);
        $plans->shouldReceive('retrieve')->once()->with('price_premium', [])->andReturn(\Stripe\Plan::constructFrom(['id' => 'price_premium', 'amount' => 1200, 'currency' => 'eur']));
        $client->shouldReceive('getService')->with('plans')->andReturn($plans);
        $controller = new class($client) extends CheckoutController {
            public function __construct(private StripeClient $client) {}
            protected function stripeClient(): StripeClient { return $this->client; }
        };
        $this->app->instance(CheckoutController::class, $controller);
        $this->postJson('/api/v1/stripe/plan-details', ['plan_id' => 'price_unknown'])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
        $this->postJson('/api/v1/stripe/plan-details', ['plan_id' => 'price_premium'])->assertOk()->assertJsonPath('amount', 1200);
    }
}
