<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayMongoRefundService
{
    private const REFUND_STATUSES = ['pending', 'processing', 'succeeded', 'failed'];

    /** @return array<string, mixed>|null */
    public function create(string $paymentId, int $amountInCentavos, string $idempotencyKey, string $notes): ?array
    {
        if (! str_starts_with($paymentId, 'pay_') || $amountInCentavos < 100 || blank($idempotencyKey)) {
            return $this->rejected();
        }

        $response = $this->request('post', '/refunds', [
            'data' => ['attributes' => [
                'payment_id' => $paymentId,
                'amount' => $amountInCentavos,
                'reason' => 'requested_by_customer',
                'notes' => Str::limit($notes, 255, ''),
                'metadata' => ['refund_request_id' => $idempotencyKey],
            ]],
        ], $idempotencyKey);

        if ($response === null || $response->serverError()) {
            return null;
        }
        if ($response->clientError()) {
            return $this->rejected();
        }

        return $response->successful() ? $this->normalizeRefund($response->json('data')) : null;
    }

    /** @return array<string, mixed>|null */
    public function fetch(string $refundId): ?array
    {
        $response = $this->request('get', '/refunds/'.rawurlencode($refundId));

        return $response?->successful() ? $this->normalizeRefund($response->json('data')) : null;
    }

    /** @return array{id: string, attributes: array<string, mixed>}|null */
    public function payment(string $paymentId): ?array
    {
        $response = $this->request('get', '/payments/'.rawurlencode($paymentId));
        $data = $response?->successful() ? $response->json('data') : null;
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_array($data['attributes'] ?? null)) {
            return null;
        }

        return ['id' => $data['id'], 'attributes' => $data['attributes']];
    }

    /** @return array{data: list<array<string, mixed>>, has_more: bool}|null */
    public function listForPayment(string $paymentId, ?string $after = null): ?array
    {
        $query = ['data.attributes.payment_id' => $paymentId];
        if ($after !== null) {
            $query['data.attributes.after'] = $after;
        }
        $response = $this->request('get', '/refunds', $query);
        $data = $response?->successful() ? $response->json('data') : null;
        $hasMore = $response?->successful() ? $response->json('has_more') : null;
        if (! is_array($data) || ! array_is_list($data) || ! is_bool($hasMore)) {
            return null;
        }

        $refunds = [];
        foreach ($data as $item) {
            $refund = $this->normalizeRefund($item);
            if ($refund === null || $refund['payment_id'] !== $paymentId) {
                return null;
            }
            $refunds[] = $refund;
        }

        return ['data' => $refunds, 'has_more' => $hasMore];
    }

    private function request(string $method, string $path, array $payload = [], ?string $idempotencyKey = null): ?Response
    {
        $key = config('services.paymongo.secret');
        if (blank($key)) {
            return null;
        }

        try {
            $client = Http::withBasicAuth($key, '')->acceptJson()->connectTimeout(3)->timeout(10);
            if ($idempotencyKey !== null) {
                $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
            }
            $url = 'https://api.paymongo.com/v1'.$path;
            $response = $method === 'post' ? $client->post($url, $payload) : $client->get($url, $payload);
            if ($response->failed()) {
                Log::warning('PayMongo refund request was not successful', ['status' => $response->status()]);
            }

            return $response;
        } catch (ConnectionException) {
            Log::warning('PayMongo refund request could not be verified');

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function normalizeRefund(mixed $data): ?array
    {
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! str_starts_with($data['id'], 'ref_')
            || ! is_array($data['attributes'] ?? null)) {
            return null;
        }

        $attributes = $data['attributes'];
        if (! in_array($attributes['status'] ?? null, self::REFUND_STATUSES, true)
            || ! is_int($attributes['amount'] ?? null) || $attributes['amount'] < 100
            || ! is_string($attributes['payment_id'] ?? null) || ! str_starts_with($attributes['payment_id'], 'pay_')) {
            return null;
        }

        return [
            'id' => $data['id'], 'status' => $attributes['status'], 'amount' => $attributes['amount'],
            'payment_id' => $attributes['payment_id'],
            'currency' => is_string($attributes['currency'] ?? null) ? $attributes['currency'] : null,
            'livemode' => is_bool($attributes['livemode'] ?? null) ? $attributes['livemode'] : null,
            'notes' => is_string($attributes['notes'] ?? null) ? $attributes['notes'] : null,
            'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [],
        ];
    }

    /** @return array{status: string, error: string} */
    private function rejected(): array
    {
        return ['status' => 'rejected', 'error' => 'PayMongo could not accept this refund request.'];
    }
}
