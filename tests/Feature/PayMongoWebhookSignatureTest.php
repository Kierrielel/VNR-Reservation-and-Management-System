<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayMongoWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-signing-secret';

    private Booking $booking;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config([
            'services.paymongo.webhook_secret' => self::SECRET,
            'services.paymongo.webhook_livemode' => false,
        ]);
        $this->booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => today()->toDateString(), 'check_out' => today()->toDateString(),
            'guest_count' => 1, 'total_price' => 1000, 'reservation_fee' => 200,
            'balance' => 800, 'status' => 'pending', 'payment_status' => 'unpaid',
        ]);
        $this->payment = Payment::create([
            'booking_id' => $this->booking->id, 'method' => 'gcash', 'amount' => 200,
            'status' => 'pending', 'checkout_session_id' => 'checkout-signed',
        ]);
    }

    #[DataProvider('modes')]
    public function test_valid_signed_webhooks_confirm_payment_in_each_mode(bool $live): void
    {
        config(['services.paymongo.webhook_livemode' => $live]);
        $body = $this->body($live);
        $header = $this->signature($body, $live ? 'li' : 'te');

        $this->deliver($body, $header)->assertOk();
        $this->deliver($body, $header)->assertOk();
        $this->assertSame('paid', $this->payment->fresh()->status);
        $this->assertSame('confirmed', $this->booking->fresh()->status);
        $this->assertSame('payment-signed', $this->payment->fresh()->reference_no);
        $this->assertDatabaseCount('payments', 1);
    }

    public static function modes(): array
    {
        return ['test mode' => [false], 'live mode' => [true]];
    }

    public function test_tampered_body_is_rejected_before_payment_updates(): void
    {
        $body = $this->body();
        $this->deliver(str_replace('payment-signed', 'payment-forged', $body), $this->signature($body))
            ->assertUnauthorized();
        $this->assertUnpaid();
    }

    #[DataProvider('invalidHeaders')]
    public function test_invalid_headers_do_not_update_payments(string $header): void
    {
        $this->deliver($this->body(), $header)->assertUnauthorized();
        $this->assertUnpaid();
    }

    public static function invalidHeaders(): array
    {
        return [
            'missing' => [''],
            'malformed' => ['not-a-signature'],
            'missing timestamp' => ['te='.str_repeat('a', 64)],
            'invalid timestamp' => ['t=invalid,te='.str_repeat('a', 64)],
            'invalid digest' => ['t=123,te=invalid'],
            'duplicate timestamp' => ['t=123,t=456,te='.str_repeat('a', 64)],
            'duplicate digest' => ['t=123,te='.str_repeat('a', 64).',te='.str_repeat('b', 64)],
        ];
    }

    public function test_incorrect_secret_and_wrong_mode_are_rejected(): void
    {
        $body = $this->body();
        $this->deliver($body, $this->signature($body, secret: 'wrong-secret'))->assertUnauthorized();
        $this->deliver($body, $this->signature($body, 'li'))->assertUnauthorized();
        config(['services.paymongo.webhook_livemode' => true]);
        $this->deliver($body, $this->signature($body, 'te'))->assertUnauthorized();
        $this->assertUnpaid();
    }

    public function test_expired_and_future_signatures_are_rejected(): void
    {
        $body = $this->body();
        foreach ([-301, 301] as $offset) {
            $this->deliver($body, $this->signature($body, timestamp: now()->timestamp + $offset))
                ->assertUnauthorized();
        }
        $this->assertUnpaid();
    }

    public function test_missing_signing_secret_fails_closed(): void
    {
        config(['services.paymongo.webhook_secret' => null]);
        $body = $this->body();
        $this->deliver($body, $this->signature($body))->assertStatus(503);
        $this->assertUnpaid();
    }

    public function test_signature_uses_raw_json_including_whitespace(): void
    {
        $body = $this->body();
        $reformatted = json_encode(json_decode($body, true));
        $this->deliver($body, $this->signature($reformatted))->assertUnauthorized();
        $this->assertUnpaid();
        $this->deliver($body, $this->signature($body))->assertOk();
        $this->assertSame('paid', $this->payment->fresh()->status);
    }

    private function body(bool $live = false): string
    {
        return json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid', 'livemode' => $live,
            'data' => ['id' => 'checkout-signed', 'attributes' => [
                'payments' => [['id' => 'payment-signed']],
            ]],
        ]]], JSON_PRETTY_PRINT);
    }

    private function signature(string $body, string $mode = 'te', ?int $timestamp = null, string $secret = self::SECRET): string
    {
        $timestamp ??= now()->timestamp;
        $digest = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $mode === 'li' ? "t={$timestamp},te=,li={$digest}" : "t={$timestamp},te={$digest},li=";
    }

    private function deliver(string $body, string $header): TestResponse
    {
        return $this->call('POST', route('webhooks.paymongo'), server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $header,
        ], content: $body);
    }

    private function assertUnpaid(): void
    {
        $this->assertSame('pending', $this->payment->fresh()->status);
        $this->assertNull($this->payment->fresh()->reference_no);
        $this->assertSame('unpaid', $this->booking->fresh()->payment_status);
        $this->assertSame('pending', $this->booking->fresh()->status);
    }
}
