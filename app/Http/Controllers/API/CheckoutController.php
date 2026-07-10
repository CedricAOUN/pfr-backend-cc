<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
  public function create(Request $request)
  {
    $user = $request->user();

    if ($user->subscribed('default')) {
      abort(409, 'You already have an active subscription.');
    }

    $validated = $request->validate([
      'product'  => ['required', Rule::in(['premium', 'chef'])],
      'interval' => ['required', Rule::in(['monthly', '6_months', 'annual'])],
    ]);

    $priceId = config("plans.{$validated['product']}.{$validated['interval']}");

    if (! $priceId) {
      abort(422, 'Invalid plan selection.');
    }

    $checkoutSession = $request->user()
      ->newSubscription('default', $priceId)
      ->checkout([
        'success_url' => config('app.frontend_url') . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => config('app.frontend_url') . '/billing/cancel',
      ]);

    return response()->json([
      'checkout_url' => $checkoutSession->url,
    ]);
  }

  function orderDetails(string $sessionId)
  {
    $validated = ['session_id' => $sessionId];

    $validated = validator($validated, [
      'session_id' => ['required', 'string'],
    ])->validate();

    if (!$validated['session_id']) {
      abort(422, 'Invalid session ID.');
    }

    $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
    $session = $stripe->checkout->sessions->retrieve($validated['session_id'], []);
    return $session;
  }

  function planDetails(Request $request)
  {
    $validated = $request->validate([
      'plan_id' => ['required', 'string'],
    ]);
    if (!$validated['plan_id']) {
      abort(422, 'Invalid plan selection.');
    }

    $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
    $plan = $stripe->plans->retrieve($validated['plan_id'], []);
    return $plan;
  }
}
