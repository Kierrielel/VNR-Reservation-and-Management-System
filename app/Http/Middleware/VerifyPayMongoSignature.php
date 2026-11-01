<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPayMongoSignature
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.paymongo.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            return response()->json(['message' => 'Webhook verification is not configured.'], 503);
        }

        if (! $this->hasValidSignature($request, $secret)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }

    private function hasValidSignature(Request $request, string $secret): bool
    {
        $header = $request->header('Paymongo-Signature', '');
        $parts = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2 || ! in_array($pair[0], ['t', 'te', 'li'], true)
                || array_key_exists($pair[0], $parts)) {
                return false;
            }

            $parts[$pair[0]] = $pair[1];
        }

        $timestamp = $parts['t'] ?? '';
        $mode = config('services.paymongo.webhook_livemode') ? 'li' : 'te';
        $signature = $parts[$mode] ?? '';

        if (! preg_match('/\A[0-9]{1,12}\z/', $timestamp)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $signature)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return false;
        }

        // PayMongo signs the exact request bytes, including whitespace and key order.
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
