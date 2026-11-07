<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\PaymentConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RefundApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paymongo.secret' => 'sk_test_refund_fixture']);
    }

    #[TestWith([null])]
    #[TestWith(['guest'])]
    #[TestWith(['staff'])]
    public function test_only_admins_can_review_or_refresh_refunds(?string $role): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role]));
        }
        $expected = $role === null ? 401 : 403;
        $this->patchJson(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertStatus($expected);
        $this->patchJson(route('admin.bookings.refund', $booking), ['decision' => 'reject', 'rejection_reason' => 'Not approved.'])->assertStatus($expected);
        $this->postJson(route('admin.bookings.refund.refresh', $booking))->assertStatus($expected);
        $this->assertSame('pending_approval', $booking->fresh()->refund_status);
        $this->assertDatabaseCount('payment_refunds', 0);
        Http::assertNothingSent();
    }

    public function test_disapproval_requires_a_reason_and_is_preserved_on_repeated_payment_confirmation(): void
    {
        Http::preventStrayRequests();
        [$booking, $payment] = $this->booking();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson(route('admin.bookings.refund', $booking), ['decision' => 'reject'])
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->patchJson(route('admin.bookings.refund', $booking), ['decision' => 'reject', 'rejection_reason' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->patch(route('admin.bookings.refund', $booking), ['decision' => 'reject', 'rejection_reason' => '  Duplicate request verified by the resort.  '])
            ->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertSame('rejected', $booking->fresh()->refund_status);
        $this->assertSame($admin->id, $booking->fresh()->refund_decided_by);
        $this->assertSame('Duplicate request verified by the resort.', $booking->fresh()->refund_rejection_reason);
        app(PaymentConfirmationService::class)->confirm($payment, 'repeat-paid-callback');
        $this->assertSame('rejected', $booking->fresh()->refund_status);
        $this->patchJson(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertUnprocessable();
        $this->assertDatabaseCount('payment_refunds', 0);
        Http::assertNothingSent();
    }

    public function test_approval_uses_the_server_payment_amount_and_pending_gateway_result_is_not_marked_refunded(): void
    {
        Http::preventStrayRequests();
        [$booking, $payment] = $this->booking();
        $this->fakeGateway($booking);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $booking), [
            'decision' => 'approve', 'amount' => 1, 'payment_id' => 'pay_attacker',
        ])->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertSame('processing', $booking->fresh()->refund_status);
        $this->assertSame($admin->id, $booking->fresh()->refund_decided_by);
        $refund = PaymentRefund::sole();
        $this->assertSame($payment->id, $refund->payment_id);
        $this->assertSame('200.00', $refund->amount);
        $this->assertSame('ref_123', $refund->gateway_refund_id);
        $this->assertSame('pending', $refund->status);
        $this->assertNull($refund->refunded_at);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.paymongo.com/v1/refunds'
            && $request['data']['attributes']['amount'] === 20000
            && $request['data']['attributes']['payment_id'] === 'pay_fee123');
        $this->patchJson(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertUnprocessable();
        $this->assertSubmissionCount(1);
    }

    public function test_successful_gateway_refund_is_recorded_without_removing_the_paid_collection(): void
    {
        Http::preventStrayRequests();
        [$booking, $payment] = $this->booking();
        $this->fakeGateway($booking, 'succeeded');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])
            ->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertSame('refunded', $booking->fresh()->refund_status);
        $this->assertSame('succeeded', PaymentRefund::sole()->status);
        $this->assertNotNull(PaymentRefund::sole()->refunded_at);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('200.00', $payment->fresh()->amount);
        app(PaymentConfirmationService::class)->confirm($payment, 'repeat-callback');
        $this->assertSame('refunded', $booking->fresh()->refund_status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_refunds', 1);
    }

    public function test_admin_sees_the_cancellation_reason_and_both_review_actions_without_rendering_guest_html(): void
    {
        [$booking] = $this->booking(['cancellation_reason' => '<script>alert(1)</script>']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('Cancellation reason')->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Approve refund')->assertSee('Disapprove refund')
            ->assertSee('name="rejection_reason"', false);
    }

    public function test_guest_sees_the_disapproval_reason_and_keeps_the_reservation_cancelled(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $booking), [
            'decision' => 'reject', 'rejection_reason' => 'Duplicate request verified by the resort.',
        ])->assertRedirect();
        $this->actingAs($booking->user)->get(route('reservations.show', $booking))->assertOk()
            ->assertSee('My schedule changed.')->assertSee('disapproved your refund request')
            ->assertSee('Duplicate request verified by the resort.');
        $this->assertSame('cancelled', $booking->fresh()->status);
        Http::assertNothingSent();
    }

    #[TestWith(['cash', null, 'counter-receipt'])]
    #[TestWith(['gcash', 'cs_fee123', null])]
    public function test_cash_or_unidentified_payments_cannot_be_submitted_to_paymongo(string $method, ?string $checkout, ?string $reference): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking([], ['method' => $method, 'checkout_session_id' => $checkout, 'reference_no' => $reference]);
        $this->fakeGateway($booking);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patchJson(route('admin.bookings.refund', $booking), ['decision' => 'approve'])
            ->assertUnprocessable();
        $this->assertSubmissionCount(0);
    }

    public function test_gateway_rejection_records_failure_instead_of_claiming_a_refund_completed(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $this->fakeGateway($booking, refundResponse: Http::response([
            'errors' => [['code' => 'refund_not_allowed', 'detail' => 'This payment cannot be refunded.']],
        ], 422));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])
            ->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertSame('failed', $booking->fresh()->refund_status);
        $this->assertSame('failed', PaymentRefund::sole()->status);
        $this->assertNull(PaymentRefund::sole()->refunded_at);
    }

    public function test_admin_can_refresh_a_pending_refund_until_paymongo_confirms_success(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $this->fakeGateway($booking, refreshStatus: 'succeeded');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertRedirect();
        $this->post(route('admin.bookings.refund.refresh', $booking))->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertSame('refunded', $booking->fresh()->refund_status);
        $this->assertSame('succeeded', PaymentRefund::sole()->status);
        $this->assertNotNull(PaymentRefund::sole()->refunded_at);
        $this->assertSubmissionCount(1);
    }

    public function test_a_timeout_is_reconciled_without_blindly_submitting_another_refund(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $attempts = 0;
        $this->fakeGateway($booking, refundResponse: function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Refund response timed out.');
        });
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('needs_review', $booking->fresh()->refund_status);
        $this->assertSame('unknown', PaymentRefund::sole()->status);
        $this->post(route('admin.bookings.refund.refresh', $booking))->assertRedirect();
        $this->assertSame('needs_review', $booking->fresh()->refund_status);
        $this->assertNull(PaymentRefund::sole()->refunded_at);
        $this->assertSame(1, $attempts);
    }

    public function test_scheduled_reconciliation_checks_an_existing_refund_without_submitting_it_again(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $this->fakeGateway($booking, refreshStatus: 'succeeded');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('pending', PaymentRefund::sole()->status);
        $this->artisan('refunds:reconcile')->expectsOutput('Refunds checked: 1; deferred: 0.')->assertSuccessful();
        $this->assertSame('refunded', $booking->fresh()->refund_status);
        $this->assertSame('succeeded', PaymentRefund::sole()->status);
        $this->assertNotNull(PaymentRefund::sole()->refunded_at);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.paymongo.com/v1/refunds/ref_123');
        $this->artisan('refunds:reconcile')->expectsOutput('Refunds checked: 0; deferred: 0.')->assertSuccessful();
        $this->assertSubmissionCount(1);
        $this->assertDatabaseCount('payment_refunds', 1);
    }

    public function test_a_new_payment_after_a_completed_refund_requires_review_and_does_not_trigger_another_refund(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $this->fakeGateway($booking, 'succeeded');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertRedirect();
        $booking->refresh();
        $decidedAt = $booking->refund_decided_at;
        $this->assertSame('refunded', $booking->refund_status);
        $extraPayment = $booking->payments()->create([
            'method' => 'gcash', 'amount' => 200, 'status' => 'pending',
            'checkout_session_id' => 'cs_late123',
        ]);
        app(PaymentConfirmationService::class)->confirm($extraPayment, 'pay_late123');
        $booking->refresh();
        $this->assertSame('needs_review', $booking->refund_status);
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('200.00', $booking->refund_amount);
        $this->assertSame($admin->id, $booking->refund_decided_by);
        $this->assertTrue($booking->refund_decided_at->equalTo($decidedAt));
        $this->assertSame('paid', $extraPayment->fresh()->status);
        $this->assertSame('succeeded', PaymentRefund::sole()->status);
        $this->assertSame('ref_123', PaymentRefund::sole()->gateway_refund_id);
        $this->artisan('refunds:reconcile')->expectsOutput('Refunds checked: 1; deferred: 0.')->assertSuccessful();
        $this->assertSame('needs_review', $booking->fresh()->refund_status);
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertSubmissionCount(1);
    }

    public function test_original_gateway_payment_time_prevents_a_delayed_confirmation_from_extending_refund_eligibility(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $this->fakeGateway($booking, paymentResponse: Http::response($this->chargeResponse([
            'paid_at' => now()->subHours(25)->timestamp,
        ])));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertRedirect();
        $this->assertContains($booking->fresh()->refund_status, ['failed', 'needs_review']);
        $this->assertSubmissionCount(0);
        $this->assertNull(PaymentRefund::sole()->refunded_at);
    }

    public function test_an_uncertain_submission_is_matched_to_the_existing_gateway_refund_without_another_post(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $attempts = 0;
        $this->fakeGateway($booking, refundResponse: function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Refund response timed out.');
        }, listResponse: fn () => Http::response([
            'data' => [['id' => 'ref_recovered123', 'attributes' => [
                'status' => 'succeeded', 'amount' => 20000, 'payment_id' => 'pay_fee123',
                'currency' => 'PHP', 'livemode' => false, 'notes' => PaymentRefund::sole()->notes,
            ]]],
            'has_more' => false,
        ]));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('needs_review', $booking->fresh()->refund_status);
        $this->post(route('admin.bookings.refund.refresh', $booking))->assertRedirect();
        $this->assertSame('refunded', $booking->fresh()->refund_status);
        $this->assertSame('succeeded', PaymentRefund::sole()->status);
        $this->assertSame('ref_recovered123', PaymentRefund::sole()->gateway_refund_id);
        $this->assertNotNull(PaymentRefund::sole()->refunded_at);
        $this->assertSame(1, $attempts);
        $this->assertDatabaseCount('payment_refunds', 1);
    }

    public function test_temporary_payment_verification_failure_is_retried_before_any_refund_submission(): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking();
        $this->fakeGateway($booking, paymentResponse: Http::sequence()
            ->push(['errors' => [['detail' => 'Temporarily unavailable.']]], 503)
            ->push($this->chargeResponse()));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('processing', $booking->fresh()->refund_status);
        $this->assertSame('queued', PaymentRefund::sole()->status);
        $this->assertSubmissionCount(0);
        $this->post(route('admin.bookings.refund.refresh', $booking))->assertRedirect();
        $this->assertSame('pending', PaymentRefund::sole()->status);
        $this->assertSame('processing', $booking->fresh()->refund_status);
        $this->assertSubmissionCount(1);
    }

    #[TestWith(['not_eligible'])]
    #[TestWith(['not_required'])]
    #[TestWith(['processing'])]
    #[TestWith(['refunded'])]
    public function test_admin_cannot_approve_a_request_that_is_not_waiting_for_review(string $status): void
    {
        Http::preventStrayRequests();
        [$booking] = $this->booking(['refund_status' => $status]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patchJson(route('admin.bookings.refund', $booking), ['decision' => 'approve'])->assertUnprocessable();
        $this->assertSame($status, $booking->fresh()->refund_status);
        Http::assertNothingSent();
    }

    /** @return array{Booking, Payment} */
    private function booking(array $bookingAttributes = [], array $paymentAttributes = []): array
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $booking = Booking::create(array_merge([
            'user_id' => $guest->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => today()->addDay(), 'check_out' => today()->addDay(), 'guest_count' => 2,
            'total_price' => 1000, 'reservation_fee' => 200, 'balance' => 800,
            'status' => 'cancelled', 'payment_status' => 'paid', 'reference_code' => 'VNR-REFUND-TEST',
            'cancelled_at' => now(), 'cancelled_by' => $guest->id, 'cancellation_reason' => 'My schedule changed.',
            'refund_status' => 'pending_approval', 'refund_amount' => 200, 'refund_deadline_at' => now()->addHours(23),
        ], $bookingAttributes));
        $payment = $booking->payments()->create(array_merge([
            'method' => 'gcash', 'amount' => 200, 'status' => 'paid', 'paid_at' => now()->subHour(),
            'reference_no' => 'pay_fee123', 'checkout_session_id' => 'cs_fee123',
        ], $paymentAttributes));

        return [$booking, $payment];
    }

    private function fakeGateway(Booking $booking, string $status = 'pending', mixed $refundResponse = null, ?string $refreshStatus = null, mixed $paymentResponse = null, mixed $listResponse = null): void
    {
        $refund = ['data' => ['id' => 'ref_123', 'attributes' => [
            'status' => $status, 'amount' => 20000, 'payment_id' => 'pay_fee123', 'currency' => 'PHP', 'livemode' => false,
        ]]];
        $refreshed = $refund;
        $refreshed['data']['attributes']['status'] = $refreshStatus ?? $status;
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/cs_fee123' => Http::response(['data' => [
                'id' => 'cs_fee123', 'attributes' => [
                    'metadata' => ['booking_id' => $booking->id],
                    'payments' => [['id' => 'pay_fee123', 'attributes' => ['status' => 'paid']]],
                ],
            ]]),
            'api.paymongo.com/v1/payments/pay_fee123' => $paymentResponse ?? Http::response($this->chargeResponse()),
            'api.paymongo.com/v1/refunds' => $refundResponse ?? Http::response($refund),
            'api.paymongo.com/v1/refunds/ref_123' => Http::response($refreshed),
            'api.paymongo.com/v1/refunds?*' => $listResponse ?? Http::response(['data' => [], 'has_more' => false]),
        ]);
    }

    private function chargeResponse(array $attributes = []): array
    {
        return ['data' => [
            'id' => 'pay_fee123', 'attributes' => array_merge([
                'status' => 'paid', 'amount' => 20000, 'currency' => 'PHP', 'livemode' => false,
                'refunds' => [], 'paid_at' => now()->subHour()->timestamp,
            ], $attributes),
        ]];
    }

    private function assertSubmissionCount(int $count): void
    {
        $submissions = Http::recorded(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.paymongo.com/v1/refunds');

        $this->assertCount($count, $submissions);
    }
}
