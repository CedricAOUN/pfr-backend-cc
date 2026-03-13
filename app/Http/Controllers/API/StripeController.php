<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeController extends Controller
{
  /**
   * Create a Stripe Checkout Session for the authenticated user.
   * Requires: { "package": "premium_monthly" | "premium_yearly" | "expert_monthly" | "expert_yearly" }
   */
  public function createCheckoutSession(Request $request)
  {
    $validated = $request->validate([
      'package' => 'required|string|in:premium_monthly,premium_yearly,expert_monthly,expert_yearly',
    ]);

    $packages = config('services.stripe.packages');
    $package  = $packages[$validated['package']];

    Stripe::setApiKey(config('services.stripe.secret'));

    $user = $request->user();

    $session = Session::create([
      'payment_method_types' => ['card'],
      'line_items' => [[
        'price'    => $package['price_id'],
        'quantity' => 1,
      ]],
      'mode'        => 'payment',
      'success_url' => config('app.frontend_url') . '/premium/success?session_id={CHECKOUT_SESSION_ID}',
      'cancel_url'  => config('app.frontend_url') . '/premium/cancel',
      'metadata'    => [
        'user_id' => $user->id,
        'package' => $validated['package'],
      ],
    ]);

    return response()->json(['checkout_url' => $session->url]);
  }

  /**
   * Handle Stripe webhook events.
   * This endpoint is PUBLIC but secured by Stripe's signature verification.
   * Only Stripe can produce a valid signature using the shared webhook secret.
   */
  public function handleWebhook(Request $request)
  {
    $payload   = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');
    $secret    = config('services.stripe.webhook_secret');

    try {
      $event = Webhook::constructEvent($payload, $sigHeader, $secret);
    } catch (SignatureVerificationException $e) {
      return response()->json(['message' => 'Invalid signature'], 400);
    }

    if ($event->type === 'checkout.session.completed') {
      $session = $event->data->object;

      // Ignore sessions where payment is not yet settled (e.g. bank transfers)
      if ($session->payment_status !== 'paid') {
        return response()->json(['message' => 'Awaiting payment'], 200);
      }

      $userId  = $session->metadata->user_id ?? null;
      $package = $session->metadata->package  ?? null;

      if (!$userId || !$package) {
        return response()->json(['message' => 'Missing metadata'], 400);
      }

      $packages = config('services.stripe.packages');
      if (!isset($packages[$package])) {
        return response()->json(['message' => 'Unknown package'], 400);
      }

      $user = User::find($userId);
      if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
      }

      $days = $packages[$package]['days'];

      // Extend from today or from current expiry if still active, whichever is later
      $base = ($user->is_premium && $user->premium_expire && $user->premium_expire > now())
        ? $user->premium_expire
        : now();

      $user->is_premium     = true;
      $user->premium_expire = \Carbon\Carbon::parse($base)->addDays($days)->toDateString();

      // Expert subscription also grants expert status
      if ($packages[$package]['grants_expert']) {
        $user->is_expert = true;
      }

      $user->save();
    }

    // Return 200 for all unhandled event types so Stripe doesn't retry them
    return response()->json(['message' => 'Webhook received'], 200);
  }
}
