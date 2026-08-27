<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Throwable;

class GoogleIdTokenVerifier
{
    public function verify(string $credential): ?array
    {
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '') {
            return null;
        }

        try {
            $payload = (new GoogleClient(['client_id' => $clientId]))
                ->verifyIdToken($credential);
        } catch (Throwable) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }
}
