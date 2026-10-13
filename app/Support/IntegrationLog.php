<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LucianoTonet\GroqPHP\GroqException;
use Throwable;

class IntegrationLog
{
    public static function context(array $details): array
    {
        return [
            'operation_id' => (string) Str::uuid(),
            'environment' => config('app.env'),
            'route' => request()->route()?->getName(),
            ...$details,
        ];
    }

    public static function mailContext(string $notification, int $userId): array
    {
        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        return self::context([
            'notification' => $notification,
            'user_id' => $userId,
            'mailer' => $mailer,
            'transport' => $transport,
            'delivery_mode' => in_array($transport, ['log', 'array'], true) ? 'simulated' : 'provider',
            'from_domain' => Str::afterLast((string) config('mail.from.address'), '@'),
            'from_address_configured' => (bool) config('mail.from.address'),
            'resend_key_configured' => (bool) config('services.resend.key'),
            'smtp_host' => config('mail.mailers.smtp.host'),
            'smtp_port' => config('mail.mailers.smtp.port'),
            'smtp_scheme' => config('mail.mailers.smtp.scheme'),
            'smtp_credentials_configured' => (bool) config('mail.mailers.smtp.username') && (bool) config('mail.mailers.smtp.password'),
        ]);
    }

    public static function write(string $level, string $event, array $context): void
    {
        Log::channel('integrations')->log($level, $event, $context);
    }

    public static function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    public static function error(Throwable $exception): array
    {
        // Database exceptions include SQL bindings, which can contain AI text.
        $message = $exception instanceof QueryException && $exception->getPrevious()
            ? $exception->getPrevious()->getMessage() : $exception->getMessage();

        foreach (['groq.api_key', 'services.resend.key', 'services.mailgun.secret', 'services.postmark.key', 'services.ses.key', 'services.ses.secret', 'mail.mailers.smtp.password', 'mail.mailers.smtp.username', 'mail.mailers.smtp.url'] as $key) {
            $secret = config($key);
            if (is_string($secret) && $secret !== '') $message = str_replace($secret, '[REDACTED]', $message);
        }
        $message = preg_replace('/Bearer\s+\S+|\b(?:gsk_|re_)[A-Za-z0-9_-]+/i', '[REDACTED]', $message);
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message);

        return [
            'exception_class' => $exception::class,
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'error_message' => Str::limit($message, 1500),
            'provider_error_type' => $exception instanceof GroqException ? $exception->getType() : null,
            'previous_exception_class' => $exception->getPrevious() ? $exception->getPrevious()::class : null,
        ];
    }
}
