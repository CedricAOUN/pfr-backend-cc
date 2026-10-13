<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetLink;
use App\Models\User;
use App\Support\IntegrationLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    private const INVALID_LINK = 'This password reset link is invalid or expired. Request a new link.';

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $validated['email'])->whereNull('google_id')->first();
        if ($user && $user->password) {
            Password::sendResetLink(['email' => $user->email, 'google_id' => null], function (User $user, string $token) {
                $startedAt = microtime(true);
                $context = IntegrationLog::mailContext('password_reset', $user->id);
                IntegrationLog::write('info', 'mail.send.started', $context);
                try {
                    $url = rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
                        'token' => $token, 'email' => $user->email,
                    ], '', '&', PHP_QUERY_RFC3986);
                    $expiresIn = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
                    $sent = Mail::to($user->email)->send(new PasswordResetLink($url, $expiresIn));
                    IntegrationLog::write($context['delivery_mode'] === 'simulated' ? 'warning' : 'info', 'mail.send.completed', [
                        ...$context, 'duration_ms' => IntegrationLog::duration($startedAt), 'message_id' => $sent?->getMessageId(),
                    ]);
                } catch (\Throwable $exception) {
                    Password::deleteToken($user);
                    $error = IntegrationLog::error($exception, [$token]);
                    IntegrationLog::write('error', 'mail.send.failed', [
                        ...$context, ...$error, 'duration_ms' => IntegrationLog::duration($startedAt),
                    ]);
                }
            });
        }

        return response()->json(['message' => 'If this email belongs to a MealMosaic password account, we have sent a reset link.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'], 'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        $status = DB::transaction(function () use ($validated) {
            $user = User::where('email', $validated['email'])->whereNull('google_id')->lockForUpdate()->first();
            if (! $user || ! $user->password) return Password::INVALID_TOKEN;

            return Password::reset([...$validated, 'google_id' => null], function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                event(new PasswordReset($user));
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => self::INVALID_LINK], 422);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }
}
