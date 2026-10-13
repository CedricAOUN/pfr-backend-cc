<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionCancelled;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionController extends Controller
{
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($user) {
            // Serialize repeat requests and always select the authenticated user's plan.
            $subscription = $user->subscriptions()->where('type', 'default')
                ->latest('id')->lockForUpdate()->first();

            abort_if(! $subscription || $subscription->ended(), 409, 'You do not have an active subscription.');

            if (! $subscription->canceled()) {
                $subscription->cancel();
            }

            if (! $subscription->cancellation_email_sent_at) {
                try {
                    Mail::to($user->email)->send(new SubscriptionCancelled($user->name, $subscription->ends_at));
                    $subscription->forceFill(['cancellation_email_sent_at' => now()])->save();
                } catch (\Throwable $exception) {
                    // A delivery failure must not undo the cancellation. A repeat request retries only the email.
                    Log::error('Subscription cancellation email failed', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscription->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Your subscription will not renew.',
                'ends_at' => $subscription->ends_at,
                'email_sent' => (bool) $subscription->cancellation_email_sent_at,
            ]);
        });
    }
}
