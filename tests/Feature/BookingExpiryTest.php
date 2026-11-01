<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\EventPackage;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_reservations_receive_a_server_controlled_thirty_minute_deadline(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        config(['reservations.hold_minutes' => 30]);
        $guest = User::factory()->create();
        $cottage = $this->cottage();

        $this->actingAs($guest)->post(route('reservations.store'), [
            'reservation_type' => 'public', 'stay_type' => 'day_tour', 'cottage_ids' => [$cottage->id],
            'check_in' => '2026-11-02', 'guest_count' => 1, 'expires_at' => '2099-01-01',
        ])->assertRedirect();

        $booking = Booking::sole();
        $this->assertSame('2026-11-01 12:30:00', $booking->expires_at->format('Y-m-d H:i:s'));
        $this->get(route('reservations.show', $booking))->assertOk()->assertSee('12:30')->assertSee('id="payment-form"', false);
    }

    public function test_due_unpaid_holds_expire_and_release_cottages_and_event_dates(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $booking->cottages()->attach($this->cottage());
        $package = EventPackage::create(['name' => 'Event', 'price' => 15000, 'hours' => 4, 'status' => 'available']);
        $event = $this->booking($guest, ['reservation_type' => 'event', 'event_package_id' => $package->id, 'stay_type' => null]);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 2; confirmed: 0; deferred: 0.')->assertSuccessful();

        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame('expired', $event->fresh()->status);
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('booking_cottage', 1);
        $this->actingAs($guest)->getJson(route('reservations.availability', [
            'check_in' => today()->addDay()->toDateString(), 'stay_type' => 'day_tour',
        ]))->assertOk()->assertJsonCount(1);
        $this->getJson(route('reservations.disabled-dates', ['reservation_type' => 'event']))->assertOk()->assertExactJson([]);
        $this->get(route('reservations.show', $booking))->assertOk()->assertSee('This unpaid reservation expired.')
            ->assertDontSee('id="payment-form"', false);
        Http::assertNothingSent();
        $this->artisan('reservations:expire')->expectsOutput('Expired: 0; confirmed: 0; deferred: 0.')->assertSuccessful();
    }

    #[DataProvider('protectedBookings')]
    public function test_paid_confirmed_closed_and_not_yet_due_bookings_are_preserved(array $attributes): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $booking = $this->booking(User::factory()->create(), $attributes);
        if ($attributes === []) {
            $booking->update(['expires_at' => now()->addSecond()]);
        }
        $status = $booking->status;

        $this->artisan('reservations:expire')->expectsOutput('Expired: 0; confirmed: 0; deferred: 0.')->assertSuccessful();

        $this->assertSame($status, $booking->fresh()->status);
        Http::assertNothingSent();
    }

    public static function protectedBookings(): array
    {
        return [[['payment_status' => 'paid']], [['status' => 'confirmed']], [['status' => 'cancelled']], [['status' => 'completed']], [[]]];
    }

    public function test_successful_gateway_payment_is_reconciled_without_a_browser_return_or_webhook(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $booking = $this->booking(User::factory()->create());
        $payment = $this->payment($booking);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/hold-session' => Http::response($this->checkoutResponse('active', 'paid'))]);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 0; confirmed: 1; deferred: 0.')->assertSuccessful();

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        Http::assertSentCount(1);
    }

    public function test_unpaid_gateway_session_is_closed_before_releasing_the_booking(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $booking = $this->booking(User::factory()->create());
        $payment = $this->payment($booking);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/hold-session' => Http::response($this->checkoutResponse('active', 'failed')),
            'api.paymongo.com/v1/checkout_sessions/hold-session/expire' => Http::response($this->checkoutResponse('expired')),
        ]);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 1; confirmed: 0; deferred: 0.')->assertSuccessful();

        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame('expired', $payment->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_processing_or_unavailable_gateway_keeps_the_dates_held_for_retry(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $booking = $this->booking(User::factory()->create());
        $this->payment($booking);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/hold-session' => Http::response($this->checkoutResponse('active', 'processing')),
            'api.paymongo.com/v1/checkout_sessions/hold-session/expire' => Http::response([], 400),
        ]);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 0; confirmed: 0; deferred: 1.')->assertSuccessful();

        $this->assertSame('pending', $booking->fresh()->status);
        Http::assertSentCount(3);
    }

    public function test_expire_rejection_followed_by_a_paid_session_confirms_the_booking(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $booking = $this->booking(User::factory()->create());
        $this->payment($booking);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/hold-session' => Http::sequence()->push($this->checkoutResponse('active'))->push($this->checkoutResponse('active', 'paid')),
            'api.paymongo.com/v1/checkout_sessions/hold-session/expire' => Http::response([], 400),
        ]);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 0; confirmed: 1; deferred: 0.')->assertSuccessful();

        $this->assertSame('confirmed', $booking->fresh()->status);
        Http::assertSentCount(3);
    }

    public function test_missing_credentials_defer_existing_sessions_and_deadlines_block_new_checkout(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => null]);
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->payment($booking);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 0; confirmed: 0; deferred: 1.')->assertSuccessful();
        $this->actingAs($guest)->from(route('reservations.show', $booking))
            ->post(route('payment.store', $booking), ['method' => 'gcash'])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        Http::assertNothingSent();
    }

    public function test_failed_payment_record_does_not_confirm_a_booking_on_return(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $payment = $this->payment($booking);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/hold-session' => Http::response($this->checkoutResponse('active', 'failed'))]);

        $this->actingAs($guest)->get(route('payment.success', $booking))->assertRedirect()->assertSessionHas('error');

        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_an_already_expired_gateway_session_releases_the_hold_without_another_expire_request(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $booking = $this->booking(User::factory()->create(), ['expires_at' => now()]);
        $this->payment($booking);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/hold-session' => Http::response($this->checkoutResponse('expired'))]);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 1; confirmed: 0; deferred: 0.')->assertSuccessful();

        $this->assertSame('expired', $booking->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_defers_expiry_and_does_not_release_dates(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $booking = $this->booking(User::factory()->create());
        $this->payment($booking);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/hold-session' => Http::failedConnection()]);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 0; confirmed: 0; deferred: 1.')->assertSuccessful();

        $this->assertSame('pending', $booking->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_all_legacy_checkout_sessions_must_be_closed_before_releasing_dates(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $booking = $this->booking(User::factory()->create());
        $this->payment($booking);
        Payment::create(['booking_id' => $booking->id, 'method' => 'gcash', 'amount' => 200,
            'status' => 'pending', 'checkout_session_id' => 'second-session']);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/hold-session' => Http::response($this->checkoutResponse('expired')),
            'api.paymongo.com/v1/checkout_sessions/second-session' => Http::response($this->checkoutResponse('active', 'processing')),
            'api.paymongo.com/v1/checkout_sessions/second-session/expire' => Http::response([], 400),
        ]);

        $this->artisan('reservations:expire')->expectsOutput('Expired: 0; confirmed: 0; deferred: 1.')->assertSuccessful();

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 2);
        Http::assertSentCount(4);
    }

    public function test_late_payment_return_does_not_reopen_an_expired_booking(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $guest = User::factory()->create();
        $booking = $this->booking($guest, ['status' => 'expired']);
        $payment = $this->payment($booking);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/hold-session' => Http::response($this->checkoutResponse('active', 'paid'))]);

        $this->actingAs($guest)->get(route('payment.success', $booking))->assertRedirect();

        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        Http::assertSentCount(1);
    }

    private function booking(User $guest, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $guest->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDay()->toDateString(),
            'guest_count' => 1, 'total_price' => 1000, 'reservation_fee' => 200, 'balance' => 800,
            'status' => 'pending', 'payment_status' => 'unpaid', 'expires_at' => now()->subSecond(),
        ], $attributes));
    }

    private function payment(Booking $booking): Payment
    {
        return Payment::create(['booking_id' => $booking->id, 'method' => 'gcash', 'amount' => 200,
            'status' => 'pending', 'checkout_session_id' => 'hold-session']);
    }

    private function checkoutResponse(string $status, ?string $paymentStatus = null): array
    {
        return ['data' => ['id' => 'hold-session', 'attributes' => ['status' => $status,
            'payments' => $paymentStatus === null ? [] : [['id' => 'gateway-payment', 'attributes' => ['status' => $paymentStatus]]],
        ]]];
    }

    private function cottage(): Cottage
    {
        return Cottage::create(['name' => 'Test cottage', 'type' => 'cottage', 'capacity' => 5, 'price_day' => 1000, 'status' => 'available']);
    }
}
