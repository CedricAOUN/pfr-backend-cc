<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Stripe\StripeClient;

class CheckoutController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->subscribed('default')) {
            abort(409, 'You already have an active subscription.');
        }

        $validated = $request->validate([
            'product' => ['required', Rule::in(['premium', 'chef'])],
            'interval' => ['required', Rule::in(['monthly', '6_months', 'annual'])],
        ]);

        $priceId = config("plans.{$validated['product']}.{$validated['interval']}");

        if (! $priceId) {
            abort(422, 'Invalid plan selection.');
        }

        $checkoutSession = $request->user()
            ->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => config('app.frontend_url').'/billing/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url').'/billing/cancel',
            ]);

        return response()->json([
            'checkout_url' => $checkoutSession->url,
        ]);
    }

    public function orderDetails(Request $request, string $sessionId)
    {
        $validated = ['session_id' => $sessionId];

        $validated = validator($validated, [
            'session_id' => ['required', 'string'],
        ])->validate();

        if (! $validated['session_id']) {
            abort(422, 'Invalid session ID.');
        }

        $session = $this->retrieveCheckoutSession($validated['session_id']);
        $sessionCustomer = is_string($session->customer)
          ? $session->customer
          : $session->customer?->id;

        if (! $request->user()->stripe_id || ! hash_equals((string) $request->user()->stripe_id, (string) $sessionCustomer)) {
            abort(403, 'This checkout session does not belong to you.');
        }

        return $session;
    }

    public function planDetails(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'string', Rule::in($this->configuredPriceIds())],
        ]);
        if (! $validated['plan_id']) {
            abort(422, 'Invalid plan selection.');
        }

        $plan = $this->stripeClient()->plans->retrieve($validated['plan_id'], []);

        return $plan;
    }

    /**
     * @return list<string>
     */
    private function configuredPriceIds(): array
    {
        return collect(config('plans', []))
            ->flatMap(fn (array $plan) => Arr::only($plan, ['monthly', '6_months', 'annual']))
            ->filter()
            ->values()
            ->all();
    }

    protected function stripeClient(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret') ?: env('STRIPE_SECRET'));
    }

    protected function retrieveCheckoutSession(string $sessionId): object
    {
        return $this->stripeClient()->checkout->sessions->retrieve($sessionId, []);
    }
}
