<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
  /**
   * Map of Stripe product ID (config key) => role name.
   * Add new plans here only — no other code needs to change.
   */
  protected function roleMap(): array
  {
    return [
      config('plans.premium.product') => 'premium_user',
      config('plans.chef.product') => 'chef',
    ];
  }

  protected function updatePeriodEnd(array $data): void
  {
    $stripeId = $data['id'] ?? null;
    $periodEnd = $data['items']['data'][0]['current_period_end'] ?? null;

    $user = $this->getUserByStripeId($data['customer']);

    $subscription = $user?->subscriptions()
      ->where('stripe_id', $stripeId)
      ->first();

    $subscription?->forceFill([
      'current_period_end' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
    ])->save();
  }

  /**
   * Grants the role matching the subscription's product and revokes
   * the others. Called on both `created` and `updated` so plan
   * swaps (which fire `updated`, not delete+create) are handled.
   */
  protected function syncRoleForSubscription(array $data): void
  {
    $user = User::where('stripe_id', $data['customer'] ?? null)->first();

    if (!$user) {
      Log::warning('Stripe webhook: no user found for customer', [
        'customer' => $data['customer'] ?? null,
      ]);
      return;
    }

    // Don't grant access for subscriptions that aren't actually paid/active yet
    // (e.g. incomplete, past_due, unpaid).
    if (!in_array($data['status'] ?? null, ['active', 'trialing'], true)) {
      return;
    }

    $productId = $data['items']['data'][0]['price']['product'] ?? null;

    foreach ($this->roleMap() as $product => $role) {
      if ($productId === $product) {
        $this->assignRoleToUser($user, $role);
      } else {
        $this->revokeRoleFromUser($user, $role);
      }
    }
  }

  protected function assignRoleToUser(?User $user, string $roleName): void
  {
    if ($user && !$user->hasRole($roleName)) {
      $user->assignRole($roleName);
    }
  }

  protected function revokeRoleFromUser(?User $user, string $roleName): void
  {
    if ($user && $user->hasRole($roleName)) {
      $user->removeRole($roleName);
    }
  }

  protected function sendSubscriptionEmail(User $user, string $body, string $subject): void
  {
    try {
      Mail::raw($body, function ($message) use ($user, $subject) {
        $message->to($user->email)->subject($subject);
      });

      Log::info('Stripe webhook: subscription email sent', [
        'user_id' => $user->id,
        'email' => $user->email,
        'subject' => $subject,
      ]);
    } catch (\Throwable $exception) {
      Log::error('Stripe webhook: subscription email failed', [
        'user_id' => $user->id,
        'email' => $user->email,
        'subject' => $subject,
        'error' => $exception->getMessage(),
      ]);
    }
  }

  public function handleCustomerSubscriptionUpdated(array $payload)
  {
    $response = parent::handleCustomerSubscriptionUpdated($payload);
    $data = $payload['data']['object'];
    $this->updatePeriodEnd($data);
    $this->syncRoleForSubscription($data);
    $user = User::where('stripe_id', $data['customer'] ?? null)->first();

    if (!$user) {
      Log::warning('Stripe webhook: no user found for subscription update email', [
        'customer' => $data['customer'] ?? null,
      ]);

      return $response;
    }

    return $response;
  }

  public function handleCustomerSubscriptionCreated(array $payload)
  {
    $response = parent::handleCustomerSubscriptionCreated($payload);
    $data = $payload['data']['object'];
    $this->updatePeriodEnd($data);
    $this->syncRoleForSubscription($data);
    $user = User::where('stripe_id', $data['customer'] ?? null)->first();

    if (!$user) {
      Log::warning('Stripe webhook: no user found for subscription creation email', [
        'customer' => $data['customer'] ?? null,
      ]);

      return $response;
    }

    $this->sendSubscriptionEmail(
      $user,
      'Your subscription status has been successfully activated.',
      'Subscription Activated Notification'
    );

    return $response;
  }

  public function handleCustomerSubscriptionDeleted(array $payload)
  {
    $response = parent::handleCustomerSubscriptionDeleted($payload);
    $data = $payload['data']['object'];
    $this->updatePeriodEnd($data);

    $user = User::where('stripe_id', $data['customer'] ?? null)->first();

    if (!$user) {
      Log::warning('Stripe webhook: no user found for customer on deletion', [
        'customer' => $data['customer'] ?? null,
      ]);
      return $response;
    }
    // Only downgrade if this was their last active subscription —
    // don't strip access if they hold another active plan.
    $hasOtherActiveSubscription = $user->subscriptions()
      ->where('stripe_id', '!=', $data['id'] ?? null)
      ->active()
      ->exists();

    if (!$hasOtherActiveSubscription) {
      foreach (array_values($this->roleMap()) as $role) {
        $this->revokeRoleFromUser($user, $role);
      }
      $this->assignRoleToUser($user, 'regular_user');
    }

    $this->sendSubscriptionEmail(
      $user,
      'Your subscription status has been successfully deactivated.',
      'Subscription Deactivated Notification'
    );

    return $response;
  }
}
