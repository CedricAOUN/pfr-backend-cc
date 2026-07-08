<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
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

  public function handleCustomerSubscriptionUpdated(array $payload)
  {
    Log::info('StripeWebhookController: subscription.updated fired', [
      'object' => $payload['data']['object'],
    ]);

    $response = parent::handleCustomerSubscriptionUpdated($payload);
    $this->updatePeriodEnd($payload['data']['object']);
    return $response;
  }

  public function handleCustomerSubscriptionCreated(array $payload)
  {
    $response = parent::handleCustomerSubscriptionCreated($payload);
    $this->updatePeriodEnd($payload['data']['object']);
    return $response;
  }
}
