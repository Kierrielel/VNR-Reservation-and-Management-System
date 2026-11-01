<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\PaymentConfirmationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SharedBookingServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_availability_endpoint_exclude_the_same_booked_cottage(): void
    {
        $this->travelTo(now()->startOfDay());
        $guest = User::factory()->create();
        $booked = $this->cottage('Booked cottage');
        $free = $this->cottage('Free cottage');
        $this->booking($guest)->cottages()->attach($booked);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson(route('reservations.availability', [
            'check_in' => today()->toDateString(),
            'stay_type' => 'day_tour',
        ]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $free->id);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('availableCottages', 1)
            ->assertViewHas('totalCottages', 2)->assertViewHas('occupancyRate', 50.0);
    }

    public function test_cancelled_bookings_release_cottages_and_overnight_requires_a_rate(): void
    {
        $guest = User::factory()->create();
        $dayOnly = $this->cottage('Day only', null);
        $overnight = $this->cottage('Overnight');
        $this->cottage('Unavailable')->update(['status' => 'occupied']);
        $this->booking($guest, ['status' => 'cancelled'])->cottages()->attach($overnight);
        $availability = app(AvailabilityService::class);
        $date = today()->toDateString();

        $this->assertEqualsCanonicalizing([$dayOnly->id, $overnight->id],
            $availability->availableCottages($date, $date, 'day_tour')->pluck('id')->all());
        $this->assertSame([$overnight->id],
            $availability->availableCottages($date, $date, 'overnight')->pluck('id')->all());
        $this->assertSame(['2026-12-31', '2027-01-01'],
            $availability->resolveDates('2026-12-31', 'overnight'));
    }

    public function test_event_calendar_and_validation_share_active_booking_rules(): void
    {
        $guest = User::factory()->create();
        $date = today()->addDay()->toDateString();
        $booking = $this->booking($guest, [
            'reservation_type' => 'event', 'stay_type' => null,
            'check_in' => $date, 'check_out' => $date, 'status' => 'confirmed',
        ]);
        $availability = app(AvailabilityService::class);

        $this->assertTrue($availability->eventDateTaken($date));
        $this->actingAs($guest)->getJson(route('reservations.disabled-dates', [
            'reservation_type' => 'event',
        ]))->assertOk()->assertExactJson([$date]);

        $booking->update(['status' => 'cancelled']);
        $this->assertFalse($availability->eventDateTaken($date));
        $this->getJson(route('reservations.disabled-dates', [
            'reservation_type' => 'event',
        ]))->assertOk()->assertExactJson([]);
    }

    public function test_reservation_submission_rechecks_shared_availability(): void
    {
        $guest = User::factory()->create();
        $cottage = $this->cottage('Already booked');
        $this->booking($guest)->cottages()->attach($cottage);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson(route('reservations.store'), [
            'reservation_type' => 'public',
            'stay_type' => 'day_tour',
            'cottage_ids' => [$cottage->id],
            'check_in' => today()->toDateString(),
            'guest_count' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('cottage_ids');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_payment_return_and_repeated_webhook_use_the_same_confirmation(): void
    {
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $payment = $this->payment($booking, 'checkout-return');
        Http::fake(['api.paymongo.com/v1/checkout_sessions/checkout-return' => Http::response([
            'data' => ['attributes' => ['payments' => [['id' => 'payment-return', 'attributes' => ['status' => 'paid']]]]],
        ])]);

        $this->actingAs($guest)->get(route('payment.success', $booking))
            ->assertRedirect(route('reservations.show', $booking))->assertSessionHas('success');
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);

        $webhookBooking = $this->booking($guest);
        $webhookPayment = $this->payment($webhookBooking, 'checkout-webhook');
        $payload = ['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid',
            'data' => ['id' => 'checkout-webhook', 'attributes' => [
                'payments' => [['id' => 'payment-webhook']],
            ]],
        ]]];
        config(['services.paymongo.webhook_secret' => 'webhook-test-secret', 'services.paymongo.webhook_livemode' => false]);
        $body = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'webhook-test-secret');
        $headers = ['Paymongo-Signature' => "t={$timestamp},te={$signature},li="];
        $this->postJson(route('webhooks.paymongo'), $payload, $headers)->assertOk();
        $this->postJson(route('webhooks.paymongo'), $payload, $headers)->assertOk();
        $this->assertSame('paid', $webhookPayment->fresh()->status);
        $this->assertSame('payment-webhook', $webhookPayment->fresh()->reference_no);
        $this->assertSame('paid', $webhookBooking->fresh()->payment_status);
        $this->assertSame('confirmed', $webhookBooking->fresh()->status);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_repeated_confirmation_does_not_overwrite_the_reference(): void
    {
        $booking = $this->booking(User::factory()->create());
        $payment = $this->payment($booking, 'checkout-repeat');
        $confirmation = app(PaymentConfirmationService::class);
        $confirmation->confirm($payment, 'original-reference');
        $confirmation->confirm($payment, 'repeated-reference');

        $this->assertSame('original-reference', $payment->fresh()->reference_no);
        $this->assertSame('800.00', $booking->fresh()->balance);
    }

    public function test_payment_confirmation_rolls_back_if_booking_update_fails(): void
    {
        $booking = $this->booking(User::factory()->create());
        $payment = $this->payment($booking, 'checkout-rollback');
        DB::unprepared("CREATE TRIGGER fail_booking_update BEFORE UPDATE ON bookings BEGIN SELECT RAISE(ABORT, 'Booking update failed'); END;");

        try {
            app(PaymentConfirmationService::class)->confirm($payment, 'reference');
            $this->fail('Expected the booking update to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Booking update failed', $exception->getMessage());
        }

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->reference_no);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }

    private function cottage(string $name, ?int $overnightPrice = 1500): Cottage
    {
        return Cottage::create([
            'name' => $name, 'type' => 'cottage', 'capacity' => 5,
            'price_day' => 1000, 'price_overnight' => $overnightPrice, 'status' => 'available',
        ]);
    }

    private function booking(User $guest, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $guest->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => today()->toDateString(), 'check_out' => today()->toDateString(),
            'guest_count' => 1, 'total_price' => 1000, 'reservation_fee' => 200,
            'balance' => 800, 'status' => 'pending', 'payment_status' => 'unpaid',
        ], $attributes));
    }

    private function payment(Booking $booking, string $session): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id, 'method' => 'gcash', 'amount' => 200,
            'status' => 'pending', 'checkout_session_id' => $session,
        ]);
    }
}
