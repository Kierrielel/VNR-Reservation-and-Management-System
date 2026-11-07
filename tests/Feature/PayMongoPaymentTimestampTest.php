<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Services\BookingExpiryService;
use App\Services\PaymentConfirmationService;
use App\Services\PayMongoCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PayMongoPaymentTimestampTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_delayed_payment_return_uses_the_original_payment_time_for_the_refund_window(): void
    {
        $this->travelTo(now()->startOfSecond());
        config(['services.paymongo.secret' => 'sk_test_timestamp']);
        Http::preventStrayRequests();
        [$guest, $booking, $payment] = $this->reservation();
        $paidAt = now()->subHours(25);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/checkout-timestamp' => Http::response([
            'data' => $this->gatewaySession($paidAt->getTimestamp()),
        ])]);

        $this->actingAs($guest)->get(route('payment.success', $booking))->assertRedirect(route('reservations.show', $booking));
        $this->assertTrue($payment->fresh()->paid_at->equalTo($paidAt));
        $this->post(route('reservations.cancel', $booking), [
            'confirm_cancellation' => 1, 'cancellation_reason' => 'My travel plans changed.',
        ])->assertRedirect(route('reservations.show', $booking));
        $this->assertSame('not_eligible', $booking->fresh()->refund_status);
    }

    public function test_legacy_checkout_without_a_payment_timestamp_keeps_the_existing_confirmation_time_fallback(): void
    {
        $this->travelTo(now()->startOfSecond());
        config(['services.paymongo.secret' => 'sk_test_timestamp']);
        Http::preventStrayRequests();
        [$guest, $booking, $payment] = $this->reservation();
        Http::fake(['api.paymongo.com/v1/checkout_sessions/checkout-timestamp' => Http::response(['data' => $this->gatewaySession(null)])]);

        $this->actingAs($guest)->get(route('payment.success', $booking))->assertRedirect();
        $this->assertTrue($payment->fresh()->paid_at->equalTo(now()));
    }

    public function test_repeated_confirmation_does_not_change_the_original_paid_timestamp(): void
    {
        $this->travelTo(now()->startOfSecond());
        [, , $payment] = $this->reservation();
        $paidAt = now()->subHours(2);
        app(PaymentConfirmationService::class)->confirm($payment, 'pay_timestamp', $paidAt);
        $this->travel(1)->hours();
        app(PaymentConfirmationService::class)->confirm($payment, 'pay_duplicate', now());

        $this->assertTrue($payment->fresh()->paid_at->equalTo($paidAt));
        $this->assertSame('pay_timestamp', $payment->fresh()->reference_no);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_expiry_reconciliation_preserves_paid_time_from_each_confirmation_branch(bool $paidDuringExpiry): void
    {
        $this->travelTo(now()->startOfSecond());
        config(['services.paymongo.secret' => 'sk_test_timestamp']);
        Http::preventStrayRequests();
        [, $booking, $payment] = $this->reservation();
        $booking->update(['expires_at' => now()->subMinute()]);
        $paidAt = now()->subHours(2);
        $paidSession = $this->gatewaySession($paidAt->getTimestamp());
        $unpaidSession = ['id' => 'checkout-timestamp', 'attributes' => ['status' => 'active', 'payments' => []]];
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/checkout-timestamp' => Http::response(['data' => $paidDuringExpiry ? $unpaidSession : $paidSession]),
            'api.paymongo.com/v1/checkout_sessions/checkout-timestamp/expire' => Http::response(['data' => $paidSession]),
        ]);

        $this->assertSame(['expired' => 0, 'confirmed' => 1, 'deferred' => 0], app(BookingExpiryService::class)->expireDue());
        $this->assertTrue($payment->fresh()->paid_at->equalTo($paidAt));
    }

    public function test_a_signed_payment_webhook_preserves_the_verified_payment_timestamp(): void
    {
        $this->travelTo(now()->startOfSecond());
        config(['services.paymongo.webhook_secret' => 'timestamp-signing-secret', 'services.paymongo.webhook_livemode' => false]);
        [, , $payment] = $this->reservation();
        $paidAt = now()->subHours(2);
        $body = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid', 'livemode' => false,
            'data' => $this->gatewaySession($paidAt->getTimestamp()),
        ]]]);
        $timestamp = now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'timestamp-signing-secret');

        $this->call('POST', route('webhooks.paymongo'), server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
        ], content: $body)->assertOk();
        $this->assertTrue($payment->fresh()->paid_at->equalTo($paidAt));
    }

    #[TestWith([null])]
    #[TestWith([0])]
    #[TestWith([-1])]
    #[TestWith(['123'])]
    #[TestWith([true])]
    #[TestWith(['future'])]
    public function test_invalid_or_future_gateway_timestamps_are_not_trusted(mixed $timestamp): void
    {
        $value = $timestamp === 'future' ? now()->addMinute()->getTimestamp() : $timestamp;

        $this->assertNull(app(PayMongoCheckoutService::class)->paidPaymentTime($this->gatewaySession($value), 'pay_timestamp'));
    }

    public function test_a_timestamp_is_not_taken_from_an_unrelated_or_failed_payment(): void
    {
        $session = $this->gatewaySession(now()->subHour()->getTimestamp());
        $checkout = app(PayMongoCheckoutService::class);
        $this->assertNull($checkout->paidPaymentTime($session, 'pay_unrelated'));
        $session['attributes']['payments'][0]['attributes']['status'] = 'failed';
        $this->assertNull($checkout->paidPaymentTime($session, 'pay_timestamp'));
    }

    private function gatewaySession(mixed $timestamp): array
    {
        return ['id' => 'checkout-timestamp', 'attributes' => ['payments' => [[
            'id' => 'pay_timestamp', 'attributes' => ['status' => 'paid', 'paid_at' => $timestamp],
        ]]]];
    }

    private function reservation(): array
    {
        $guest = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $guest->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => today()->addDay(), 'check_out' => today()->addDay(), 'guest_count' => 1,
            'total_price' => 1000, 'reservation_fee' => 200, 'balance' => 800,
            'status' => 'pending', 'payment_status' => 'unpaid',
        ]);
        $booking->forceFill(['created_at' => now()->subDays(3)])->save();
        $payment = $booking->payments()->create([
            'method' => 'gcash', 'amount' => 200, 'status' => 'pending', 'checkout_session_id' => 'checkout-timestamp',
        ]);

        return [$guest, $booking, $payment];
    }
}
