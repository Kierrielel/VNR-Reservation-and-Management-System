<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoCheckoutService
{
    public static function lockName(int $bookingId): string
    {
        return 'booking-checkout:'.$bookingId;
    }

    public function retrieve(string $sessionId): ?array
    {
        return $this->request($sessionId, false);
    }

    public function expire(string $sessionId): ?array
    {
        return $this->request($sessionId, true);
    }

    public function paidPaymentId(array $session): ?string
    {
        foreach ($session['attributes']['payments'] ?? [] as $payment) {
            if (($payment['attributes']['status'] ?? $payment['status'] ?? null) === 'paid'
                && is_string($payment['id'] ?? null)) {
                return $payment['id'];
            }
        }

        return null;
    }

    public function paidPaymentTime(array $session, string $paymentId): ?CarbonImmutable
    {
        foreach ($session['attributes']['payments'] ?? [] as $payment) {
            if (($payment['id'] ?? null) !== $paymentId
                || ($payment['attributes']['status'] ?? $payment['status'] ?? null) !== 'paid') {
                continue;
            }
            $timestamp = $payment['attributes']['paid_at'] ?? null;
            if (! is_int($timestamp) || $timestamp <= 0 || $timestamp > now()->getTimestamp()) {
                return null;
            }

            return CarbonImmutable::createFromTimestamp($timestamp, config('app.timezone'));
        }

        return null;
    }

    private function request(string $sessionId, bool $expire): ?array
    {
        $key = config('services.paymongo.secret');
        if (blank($key)) {
            return null;
        }

        try {
            $client = Http::withBasicAuth($key, '')->connectTimeout(3)->timeout(10);
            $url = 'https://api.paymongo.com/v1/checkout_sessions/'.rawurlencode($sessionId);
            $response = $expire ? $client->post($url.'/expire') : $client->get($url);
            if ($response->failed()) {
                Log::warning('PayMongo checkout verification deferred', ['session_id' => $sessionId, 'status' => $response->status()]);

                return null;
            }

            $session = $response->json('data');

            return is_array($session) && is_array($session['attributes'] ?? null) ? $session : null;
        } catch (ConnectionException) {
            Log::warning('PayMongo checkout verification unavailable', ['session_id' => $sessionId]);

            return null;
        }
    }
}
