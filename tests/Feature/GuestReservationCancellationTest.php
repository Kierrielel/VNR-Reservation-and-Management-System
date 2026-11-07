<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\PaymentConfirmationService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GuestReservationCancellationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([86399, 'pending_approval'])]
    #[TestWith([86400, 'pending_approval'])]
    #[TestWith([86401, 'not_eligible'])]
    public function test_refund_window_starts_with_fee_payment_and_includes_exactly_24_hours(int $seconds, string $status): void
    {
        $this->travelTo(now()->startOfSecond());
        Http::preventStrayRequests();
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $payment = $this->payment($booking, now()->subSeconds($seconds));
        $cottage = Cottage::create([
            'name' => 'Cancellation cottage', 'type' => 'cottage', 'capacity' => 5,
            'price_day' => 1000, 'status' => 'available',
        ]);
        $booking->cottages()->attach($cottage);

        $this->actingAs($guest)->post(route('reservations.cancel', $booking), [
            'confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.', 'refund_status' => 'pending_approval', 'refund_amount' => 99999,
        ])->assertRedirect(route('reservations.show', $booking))->assertSessionHas('success');
        $booking->refresh();

        $this->assertSame('cancelled', $booking->status);
        $this->assertSame($guest->id, $booking->cancelled_by);
        $this->assertSame('Plans changed.', $booking->cancellation_reason);
        $this->assertTrue($booking->cancelled_at->equalTo(now()));
        $this->assertSame($status, $booking->refund_status);
        $this->assertSame($status === 'pending_approval' ? '200.00' : '0.00', $booking->refund_amount);
        $this->assertTrue($booking->refund_deadline_at->equalTo($payment->paid_at->copy()->addHours(24)));
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_cottage', 1);
        $this->assertSame([$cottage->id], app(AvailabilityService::class)
            ->availableCottages($booking->check_in->toDateString(), $booking->check_out->toDateString())->pluck('id')->all());
        $this->get(route('reservations.show', $booking))->assertOk()
            ->assertSee($status === 'pending_approval' ? 'awaiting the resort administrator' : 'non-refundable');
        $this->get(route('reservations.qr', $booking))->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_unpaid_booking_cancellation_has_no_refund_and_cannot_start_a_new_checkout(): void
    {
        $guest = User::factory()->create();
        $booking = $this->booking($guest, ['status' => 'pending', 'payment_status' => 'unpaid']);
        $this->actingAs($guest)->post(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])
            ->assertRedirect(route('reservations.show', $booking));
        $this->assertSame('not_required', $booking->fresh()->refund_status);
        $this->assertSame('0.00', $booking->fresh()->refund_amount);
        $this->assertDatabaseCount('payments', 0);
        $this->post(route('payment.store', $booking), ['method' => 'gcash'])->assertRedirect()->assertSessionHas('error');
    }

    public function test_confirmation_is_required_and_only_the_owner_can_cancel(): void
    {
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->postJson(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertUnauthorized();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertForbidden();
        $this->actingAs($guest)->postJson(route('reservations.cancel', $booking), [])
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_cancellation');
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->cancelled_at);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith([['unexpected']])]
    public function test_cancellation_requires_a_nonempty_text_reason(mixed $reason): void
    {
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->actingAs($guest)->postJson(route('reservations.cancel', $booking), [
            'confirm_cancellation' => 1,
            'cancellation_reason' => $reason,
        ])->assertUnprocessable()->assertJsonValidationErrors('cancellation_reason');
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->cancelled_at);
    }

    public function test_cancellation_reason_cannot_exceed_one_thousand_characters(): void
    {
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->actingAs($guest)->postJson(route('reservations.cancel', $booking), [
            'confirm_cancellation' => 1,
            'cancellation_reason' => str_repeat('a', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors('cancellation_reason');
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    #[TestWith(['cancelled', false, false])]
    #[TestWith(['expired', false, false])]
    #[TestWith(['completed', false, false])]
    #[TestWith(['confirmed', true, false])]
    #[TestWith(['confirmed', false, true])]
    public function test_closed_checked_in_and_past_arrival_bookings_cannot_be_cancelled(string $status, bool $checkedIn, bool $past): void
    {
        $guest = User::factory()->create();
        $booking = $this->booking($guest, [
            'status' => $status, 'checked_in_at' => $checkedIn ? now() : null,
            'check_in' => $past ? today()->subDay() : today()->addDay(),
        ]);
        $this->actingAs($guest)->postJson(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_cancellation');
        $this->assertSame($status, $booking->fresh()->status);
        $this->get(route('reservations.show', $booking))->assertOk()
            ->assertDontSee('action="'.route('reservations.cancel', $booking).'"', false);
    }

    public function test_repeated_cancellation_does_not_change_the_original_time_or_refund_amount(): void
    {
        $this->freezeTime();
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->payment($booking, now()->subHour());
        $this->actingAs($guest)->post(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertRedirect();
        $cancelledAt = $booking->fresh()->cancelled_at;
        $this->travel(2)->days();
        $this->postJson(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertUnprocessable();
        $booking->refresh();
        $this->assertTrue($booking->cancelled_at->equalTo($cancelledAt));
        $this->assertSame('pending_approval', $booking->refund_status);
        $this->assertSame('200.00', $booking->refund_amount);
    }

    public function test_a_later_balance_payment_does_not_restart_the_fee_refund_window(): void
    {
        $this->freezeTime();
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->payment($booking, now()->subHours(25));
        $this->payment($booking, now(), ['amount' => 800, 'method' => 'cash']);
        $this->actingAs($guest)->post(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertRedirect();
        $this->assertSame('not_eligible', $booking->fresh()->refund_status);
        $this->assertSame('0.00', $booking->fresh()->refund_amount);
        $this->assertSame(1000.0, (float) $booking->payments()->where('status', 'paid')->sum('amount'));
    }

    public function test_late_payment_is_flagged_for_manual_refund_without_reopening_the_booking(): void
    {
        $this->freezeTime();
        $guest = User::factory()->create();
        $booking = $this->booking($guest, ['status' => 'pending', 'payment_status' => 'unpaid']);
        $payment = $this->payment($booking, now(), ['status' => 'pending', 'paid_at' => null]);
        $this->actingAs($guest)->post(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertRedirect();
        $this->assertSame('not_required', $booking->fresh()->refund_status);
        $this->travel(1)->minutes();
        app(PaymentConfirmationService::class)->confirm($payment, 'late-payment');
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('pending_approval', $booking->fresh()->refund_status);
        $this->assertSame('200.00', $booking->fresh()->refund_amount);
        $this->travel(2)->days();
        app(PaymentConfirmationService::class)->confirm($payment, 'duplicate');
        $this->assertSame('pending_approval', $booking->fresh()->refund_status);
        $this->assertSame('200.00', $booking->fresh()->refund_amount);
        $this->assertSame('late-payment', $payment->fresh()->reference_no);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_owner_sees_the_policy_and_admin_sees_the_manual_refund_amount(): void
    {
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->payment($booking, now()->subHour());
        $this->actingAs($guest)->get(route('reservations.show', $booking))->assertOk()
            ->assertSee('Cancel Reservation')->assertSee('Refund deadline:')->assertSee('24 hours')
            ->assertSee('name="cancellation_reason"', false);
        $this->post(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertRedirect();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('Guest cancelled at')->assertSee('200.00')->assertSee('awaiting admin approval');
    }

    private function booking(User $guest, array $attributes = []): Booking
    {
        $booking = Booking::create(array_merge([
            'user_id' => $guest->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => today()->addDay(), 'check_out' => today()->addDay(), 'guest_count' => 2,
            'total_price' => 1000, 'reservation_fee' => 200, 'balance' => 800,
            'status' => 'confirmed', 'payment_status' => 'paid',
        ], $attributes));
        $booking->forceFill(['created_at' => now()->subDays(4)])->save();

        return $booking;
    }

    public function test_only_completed_payments_are_included_in_the_manual_refund_amount(): void
    {
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->payment($booking, now()->subHours(2));
        $this->payment($booking, now()->subHour(), ['amount' => 800, 'method' => 'cash']);
        $this->payment($booking, now(), ['amount' => 200, 'status' => 'pending', 'paid_at' => null]);
        $this->actingAs($guest)->post(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertRedirect();
        $this->assertSame('1000.00', $booking->fresh()->refund_amount);
        $this->assertDatabaseCount('payments', 3);
    }

    public function test_legacy_paid_payment_uses_its_recorded_payment_time_when_paid_at_is_missing(): void
    {
        $this->travelTo(now()->startOfSecond());
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $payment = $this->payment($booking, now(), ['paid_at' => null]);
        $payment->forceFill(['created_at' => now()->subHours(30), 'updated_at' => now()->subHours(25)])->save();
        $this->actingAs($guest)->post(route('reservations.cancel', $booking), ['confirm_cancellation' => 1, 'cancellation_reason' => 'Plans changed.'])->assertRedirect();
        $this->assertSame('not_eligible', $booking->fresh()->refund_status);
        $this->assertTrue($booking->fresh()->refund_deadline_at->equalTo(now()->subHour()));
    }

    private function payment(Booking $booking, CarbonInterface $paidAt, array $attributes = []): Payment
    {
        return $booking->payments()->create(array_merge([
            'method' => 'gcash', 'amount' => 200, 'status' => 'paid',
            'paid_at' => $paidAt, 'reference_no' => 'fee-payment',
        ], $attributes));
    }
}
