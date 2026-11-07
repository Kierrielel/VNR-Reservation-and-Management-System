<?php

namespace Tests\Feature;

use App\Services\PayMongoRefundService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PayMongoRefundClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paymongo.secret' => 'sk_test_client']);
        Http::preventStrayRequests();
    }

    #[TestWith(['pending'])]
    #[TestWith(['processing'])]
    #[TestWith(['succeeded'])]
    #[TestWith(['failed'])]
    public function test_create_preserves_gateway_status_and_uses_the_exact_amount_and_idempotency_key(string $status): void
    {
        Http::fake(['api.paymongo.com/v1/refunds' => Http::response($this->refund($status))]);
        $result = app(PayMongoRefundService::class)->create('pay_test', 20000, 'stable-refund-key', 'Cancellation request');

        $this->assertSame($status, $result['status']);
        $this->assertSame('ref_test', $result['id']);
        $this->assertSame(20000, $result['amount']);
        $this->assertSame('pay_test', $result['payment_id']);
        $this->assertFalse($result['livemode']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_client:'))
            && $request->hasHeader('Idempotency-Key', 'stable-refund-key')
            && $request['data']['attributes'] === [
                'payment_id' => 'pay_test', 'amount' => 20000,
                'reason' => 'requested_by_customer', 'notes' => 'Cancellation request',
                'metadata' => ['refund_request_id' => 'stable-refund-key'],
            ]);
        Http::assertSentCount(1);
    }

    public function test_timeout_has_an_unknown_outcome_and_does_not_automatically_repeat_the_refund(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('simulated network failure');
        });

        $this->assertNull(app(PayMongoRefundService::class)->create('pay_test', 20000, 'stable-refund-key', 'Cancellation request'));
        $this->assertSame(1, $calls);
    }

    public function test_server_error_is_uncertain_and_is_not_retried(): void
    {
        Http::fake(['api.paymongo.com/v1/refunds' => Http::response(['errors' => [['detail' => 'private gateway detail']]], 500)]);

        $this->assertNull(app(PayMongoRefundService::class)->create('pay_test', 20000, 'stable-refund-key', 'Cancellation request'));
        Http::assertSentCount(1);
    }

    public function test_gateway_rejection_exposes_only_a_safe_static_error(): void
    {
        Http::fake(['api.paymongo.com/v1/refunds' => Http::response(['errors' => [['detail' => 'private gateway detail']]], 400)]);
        $result = app(PayMongoRefundService::class)->create('pay_test', 20000, 'stable-refund-key', 'Cancellation request');

        $this->assertSame(['status' => 'rejected', 'error' => 'PayMongo could not accept this refund request.'], $result);
        Http::assertSentCount(1);
    }

    public function test_success_with_missing_identity_or_amount_is_not_treated_as_a_confirmed_refund(): void
    {
        $body = $this->refund('succeeded');
        unset($body['data']['attributes']['payment_id']);
        Http::fake(['api.paymongo.com/v1/refunds' => Http::response($body)]);

        $this->assertNull(app(PayMongoRefundService::class)->create('pay_test', 20000, 'stable-refund-key', 'Cancellation request'));
    }

    public function test_fetch_retains_the_refund_identity_and_does_not_send_a_post_idempotency_header(): void
    {
        Http::fake(['api.paymongo.com/v1/refunds/ref_test' => Http::response($this->refund('processing'))]);
        $refund = app(PayMongoRefundService::class)->fetch('ref_test');

        $this->assertSame('processing', $refund['status']);
        $this->assertSame('ref_test', $refund['id']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && ! $request->hasHeader('Idempotency-Key'));
    }

    #[TestWith([404])]
    #[TestWith([500])]
    public function test_failed_refund_retrieval_does_not_invent_a_gateway_status(int $status): void
    {
        Http::fake(['api.paymongo.com/v1/refunds/ref_test' => Http::response([], $status)]);

        $this->assertNull(app(PayMongoRefundService::class)->fetch('ref_test'));
    }

    public function test_payment_retrieval_returns_the_gateway_identity_and_attributes_for_verification(): void
    {
        $payment = ['id' => 'pay_test', 'attributes' => ['amount' => 20000, 'currency' => 'PHP', 'livemode' => false, 'status' => 'paid']];
        Http::fake(['api.paymongo.com/v1/payments/pay_test' => Http::response(['data' => $payment])]);

        $this->assertSame($payment, app(PayMongoRefundService::class)->payment('pay_test'));
    }

    public function test_refund_listing_preserves_identity_metadata_and_pagination(): void
    {
        $body = $this->refund('succeeded');
        $body['data']['attributes']['metadata'] = ['refund_request_id' => 'stable-refund-key'];
        Http::fake(['api.paymongo.com/v1/refunds*' => Http::response(['data' => [$body['data']], 'has_more' => true])]);
        $result = app(PayMongoRefundService::class)->listForPayment('pay_test', 'ref_previous');

        $this->assertTrue($result['has_more']);
        $this->assertSame('stable-refund-key', $result['data'][0]['metadata']['refund_request_id']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request['data.attributes.payment_id'] === 'pay_test'
            && $request['data.attributes.after'] === 'ref_previous');
    }

    public function test_refund_listing_rejects_an_unrelated_payment_or_unknown_pagination_state(): void
    {
        $body = $this->refund('succeeded');
        $body['data']['attributes']['payment_id'] = 'pay_unrelated';
        Http::fake(['api.paymongo.com/v1/refunds*' => Http::response(['data' => [$body['data']], 'has_more' => false])]);

        $this->assertNull(app(PayMongoRefundService::class)->listForPayment('pay_test'));
    }

    public function test_missing_configuration_never_sends_a_refund_or_lookup_request(): void
    {
        config(['services.paymongo.secret' => null]);
        Http::fake();
        $client = app(PayMongoRefundService::class);

        $this->assertNull($client->create('pay_test', 20000, 'stable-refund-key', 'Cancellation request'));
        $this->assertNull($client->fetch('ref_test'));
        $this->assertNull($client->payment('pay_test'));
        $this->assertNull($client->listForPayment('pay_test'));
        Http::assertNothingSent();
    }

    private function refund(string $status): array
    {
        return ['data' => ['id' => 'ref_test', 'type' => 'refund', 'attributes' => [
            'status' => $status, 'payment_id' => 'pay_test', 'amount' => 20000,
            'currency' => 'PHP', 'livemode' => false, 'notes' => 'Cancellation request',
        ]]];
    }
}
