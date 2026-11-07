<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('searches')]
    public function test_filters_remain_applied_when_searching_guest_or_reference(string $search): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create(['name' => 'Target Guest', 'email' => 'target@example.com']);
        $target = $this->booking($guest, ['reference_code' => 'VNR-TARGET']);
        $this->booking($guest, ['reference_code' => 'VNR-PENDING', 'status' => 'pending']);

        $response = $this->actingAs($admin)->get(route('admin.bookings.index', [
            'search' => $search, 'status' => 'confirmed', 'type' => 'public', 'date' => today()->toDateString(),
        ]));

        $response->assertOk()->assertSee('VNR-TARGET')->assertDontSee('VNR-PENDING');
        $this->assertSame([$target->id], $response->viewData('recentBooking')->pluck('id')->all());
    }

    public static function searches(): array
    {
        return [['Target Guest'], ['target@example.com'], ['VNR-TARGET']];
    }

    #[DataProvider('restrictedRequests')]
    public function test_management_endpoints_require_an_admin(string $method, string $action, bool $authenticated): void
    {
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        if ($authenticated) {
            $this->actingAs($guest);
        }

        $response = $this->json($method, route('admin.bookings.'.$action, ['booking' => $booking->id]), [
            'status' => 'cancelled', 'method' => 'cash',
        ]);

        $authenticated ? $response->assertForbidden() : $response->assertUnauthorized();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('800.00', $booking->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    public static function restrictedRequests(): array
    {
        $cases = [];
        foreach ([false, true] as $authenticated) {
            foreach ([['GET', 'index'], ['GET', 'show'], ['PATCH', 'update'], ['POST', 'balance']] as [$method, $action]) {
                $cases[($authenticated ? 'customer ' : 'visitor ').$action] = [$method, $action, $authenticated];
            }
        }

        return $cases;
    }

    public function test_details_show_payment_history_and_escape_guest_content(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create(), ['additional_message' => '<script>alert(1)</script>']);
        $booking->cottages()->attach($this->cottage());
        $this->deposit($booking);

        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('Payment history')->assertSee('Test cottage')->assertSee('deposit-reference')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_cancellation_keeps_payment_records_and_releases_the_cottage(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $booking->cottages()->attach($this->cottage());
        $payment = $this->deposit($booking);

        $this->actingAs($admin)->patch(route('admin.bookings.update', $booking), ['status' => 'cancelled'])
            ->assertRedirect(route('admin.bookings.show', $booking));

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_cottage', 1);
        $this->actingAs($admin)->getJson(route('reservations.availability', [
            'check_in' => today()->toDateString(), 'stay_type' => 'day_tour',
        ]))->assertOk()->assertJsonCount(1);
    }

    public function test_admin_can_confirm_paid_bookings_and_complete_settled_bookings(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create(), ['status' => 'pending', 'balance' => 0]);

        $this->actingAs($admin)->patch(route('admin.bookings.update', $booking), ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->patch(route('admin.bookings.update', $booking), ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $booking->fresh()->status);
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_status_changes_return_422_without_modifying_records(array $attributes, string $target): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create(), $attributes);
        $status = $booking->status;

        $this->actingAs($admin)->patchJson(route('admin.bookings.update', $booking), ['status' => $target])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame($status, $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public static function invalidTransitions(): array
    {
        return [
            'unpaid confirmation' => [['status' => 'pending', 'payment_status' => 'unpaid'], 'confirmed'],
            'outstanding balance' => [[], 'completed'],
            'cancelled cannot reopen' => [['status' => 'cancelled'], 'confirmed'],
            'completed cannot cancel' => [['status' => 'completed'], 'cancelled'],
            'future departure' => [['balance' => 0, 'check_out' => '2099-01-01'], 'completed'],
            'unsupported status' => [[], 'pending'],
        ];
    }

    public function test_settlement_uses_server_balance_and_rejects_duplicate_payment(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create());
        $this->deposit($booking);

        $this->actingAs($admin)->post(route('admin.bookings.balance', $booking), [
            'method' => 'cash', 'amount' => 1, 'balance' => 999, 'status' => 'cancelled',
        ])->assertRedirect(route('admin.bookings.show', $booking));

        $this->assertSame('0.00', $booking->fresh()->balance);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'method' => 'cash', 'amount' => 800, 'status' => 'paid']);
        $this->postJson(route('admin.bookings.balance', $booking), ['method' => 'cash'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_payment_requires_confirmation_and_digital_reference(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create(), ['status' => 'cancelled']);

        $this->actingAs($admin)->postJson(route('admin.bookings.balance', $booking), ['method' => 'gcash'])
            ->assertUnprocessable()->assertJsonValidationErrors('reference_no');
        $this->postJson(route('admin.bookings.balance', $booking), ['method' => 'cash'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertSame('800.00', $booking->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_late_payment_is_recorded_without_reopening_a_cancelled_booking(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'test-secret']);
        $guest = User::factory()->create();
        $booking = $this->booking($guest, ['status' => 'cancelled', 'payment_status' => 'unpaid']);
        $payment = $this->deposit($booking, ['status' => 'pending', 'checkout_session_id' => 'cancelled-session']);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/cancelled-session' => Http::response([
            'data' => ['attributes' => ['payments' => [['id' => 'late-payment', 'attributes' => ['status' => 'paid']]]]],
        ])]);

        $this->actingAs($guest)->get(route('payment.success', $booking))->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->get(route('reservations.show', $booking))->assertOk()->assertDontSee('id="payment-form"', false);
    }

    public function test_cancelled_booking_cannot_start_a_new_checkout(): void
    {
        Http::preventStrayRequests();
        $guest = User::factory()->create();
        $booking = $this->booking($guest, ['status' => 'cancelled', 'payment_status' => 'unpaid']);

        $this->actingAs($guest)->from(route('reservations.show', $booking))
            ->post(route('payment.store', $booking), ['method' => 'gcash'])
            ->assertRedirect(route('reservations.show', $booking))->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_filters_are_preserved_when_navigating_booking_pages(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create(['name' => 'Filtered Guest']);
        for ($index = 1; $index <= 6; $index++) {
            $this->booking($guest, ['reference_code' => 'VNR-FILTER-'.$index]);
        }
        $this->booking($guest, ['status' => 'pending', 'reference_code' => 'VNR-EXCLUDED']);
        $query = ['search' => 'Filtered Guest', 'status' => 'confirmed'];

        $first = $this->actingAs($admin)->get(route('admin.bookings.index', $query));
        $first->assertOk()->assertDontSee('VNR-EXCLUDED');
        $this->assertStringContainsString('Showing 1 to 5 of 6 results', preg_replace('/\s+/', ' ', strip_tags($first->getContent())));
        $next = $first->viewData('recentBooking')->nextPageUrl();
        $second = $this->get($next)->assertOk()
            ->assertSee('VNR-FILTER-1')->assertDontSee('VNR-EXCLUDED');
        $this->assertStringContainsString('Showing 6 to 6 of 6 results', preg_replace('/\s+/', ' ', strip_tags($second->getContent())));
    }

    private function booking(User $guest, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $guest->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => today()->toDateString(), 'check_out' => today()->toDateString(),
            'guest_count' => 2, 'total_price' => 1000, 'reservation_fee' => 200,
            'balance' => 800, 'status' => 'confirmed', 'payment_status' => 'paid',
        ], $attributes));
    }

    private function deposit(Booking $booking, array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'booking_id' => $booking->id, 'method' => 'gcash', 'amount' => 200,
            'status' => 'paid', 'reference_no' => 'deposit-reference',
        ], $attributes));
    }

    private function cottage(): Cottage
    {
        return Cottage::create([
            'name' => 'Test cottage', 'type' => 'cottage', 'capacity' => 5,
            'price_day' => 1000, 'status' => 'available',
        ]);
    }
}
