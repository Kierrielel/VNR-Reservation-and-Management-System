<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Services\CheckInService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_customer_gets_a_private_qr_image_and_other_users_cannot_download_it(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $guest = User::factory()->create();
        $booking = $this->booking($guest);

        $this->actingAs($guest)->get(route('reservations.show', $booking))->assertOk()->assertSee('Your check-in QR code');
        $image = $this->get(route('reservations.qr', $booking));
        $image->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')->assertSee('<svg', false);
        $this->assertStringContainsString('no-store', $image->headers->get('Cache-Control'));
        $this->get(route('reservations.qr', ['booking' => $booking, 'download' => 1]))
            ->assertOk()->assertDownload('reservation-'.$booking->id.'-checkin.svg');
        $this->actingAs(User::factory()->create())->get(route('reservations.qr', $booking))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('reservations.qr', $booking))->assertForbidden();
    }

    #[DataProvider('operatorRoles')]
    public function test_admin_and_staff_can_review_then_record_one_audited_arrival(string $role): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $operator = User::factory()->create(['role' => $role]);
        $booking = $this->booking(User::factory()->create());
        $token = app(CheckInService::class)->token($booking);

        $this->actingAs($operator)->get(route('check-in.index'))->assertOk()->assertSee('Start camera')->assertSee('Scan from an image');
        $this->postJson(route('check-in.preview'), ['token' => $token])->assertOk()
            ->assertJsonPath('can_check_in', true)->assertJsonPath('reference', 'VNR-QR-TEST');
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->postJson(route('check-in.store'), ['token' => $token, 'checked_in_by' => 999])->assertOk();

        $booking->refresh();
        $this->assertSame($operator->id, $booking->checked_in_by);
        $this->assertSame('2026-11-01 12:00:00', $booking->checked_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('confirmed', $booking->status);
        $this->postJson(route('check-in.store'), ['token' => $token])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertSame($operator->id, $booking->fresh()->checked_in_by);
    }

    public static function operatorRoles(): array
    {
        return [['admin'], ['staff']];
    }

    #[DataProvider('restrictedEndpoints')]
    public function test_visitors_and_customers_cannot_access_staff_scanning(string $method, string $action, bool $authenticated): void
    {
        if ($authenticated) {
            $this->actingAs(User::factory()->create());
        }

        $response = $this->json($method, route('check-in.'.$action), ['token' => 'fake']);

        $authenticated ? $response->assertForbidden() : $response->assertUnauthorized();
        $this->assertDatabaseCount('bookings', 0);
    }

    public static function restrictedEndpoints(): array
    {
        $cases = [];
        foreach ([false, true] as $authenticated) {
            foreach ([['GET', 'index'], ['POST', 'preview'], ['POST', 'store']] as [$method, $action]) {
                $cases[($authenticated ? 'customer ' : 'visitor ').$action] = [$method, $action, $authenticated];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidBookings')]
    public function test_check_in_rejects_invalid_booking_states_and_dates(array $attributes): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $operator = User::factory()->create(['role' => 'staff']);
        $booking = $this->booking(User::factory()->create(), $attributes);

        $this->actingAs($operator)->postJson(route('check-in.store'), ['token' => app(CheckInService::class)->token($booking)])
            ->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->assertNull($booking->fresh()->checked_in_at);
        $this->assertNull($booking->fresh()->checked_in_by);
    }

    public static function invalidBookings(): array
    {
        return [
            'pending' => [['status' => 'pending']], 'cancelled' => [['status' => 'cancelled']],
            'expired' => [['status' => 'expired']], 'completed' => [['status' => 'completed']],
            'unpaid fee' => [['payment_status' => 'unpaid']], 'balance due' => [['balance' => 800]],
            'future arrival' => [['check_in' => '2026-11-02']], 'past arrival' => [['check_in' => '2026-10-31']],
        ];
    }

    public function test_staff_preview_shows_balance_without_granting_admin_access(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $staff = User::factory()->create(['role' => 'staff']);
        $booking = $this->booking(User::factory()->create(), ['balance' => 800]);

        $this->actingAs($staff)->postJson(route('check-in.preview'), ['token' => app(CheckInService::class)->token($booking)])
            ->assertOk()->assertJsonPath('balance', 800)->assertJsonPath('can_check_in', false)->assertJsonPath('manage_url', null);
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->postJson(route('admin.bookings.balance', $booking), ['method' => 'cash'])->assertForbidden();
    }

    public function test_tampered_and_unrelated_codes_cannot_record_an_arrival(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $operator = User::factory()->create(['role' => 'staff']);
        $booking = $this->booking(User::factory()->create());
        $token = app(CheckInService::class)->token($booking);
        $token[30] = $token[30] === 'A' ? 'B' : 'A';

        $this->actingAs($operator)->postJson(route('check-in.store'), ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->postJson(route('check-in.preview'), ['token' => 'https://example.test/other-qr'])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertNull($booking->fresh()->checked_in_at);
    }

    public function test_cancelled_booking_is_rechecked_after_a_successful_preview(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $operator = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create());
        $token = app(CheckInService::class)->token($booking);

        $this->actingAs($operator)->postJson(route('check-in.preview'), ['token' => $token])->assertOk();
        $this->patch(route('admin.bookings.update', $booking), ['status' => 'cancelled'])->assertRedirect();
        $this->postJson(route('check-in.store'), ['token' => $token])->assertUnprocessable();

        $this->assertNull($booking->fresh()->checked_in_at);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_checked_in_booking_cannot_be_cancelled_and_customer_sees_arrival_record(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create();
        $booking = $this->booking($guest);

        $this->actingAs($admin)->postJson(route('check-in.store'), ['token' => app(CheckInService::class)->token($booking)])->assertOk();
        $this->patchJson(route('admin.bookings.update', $booking), ['status' => 'cancelled'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->actingAs($guest)->get(route('reservations.show', $booking))->assertOk()->assertSee('Checked in on')
            ->assertDontSee('Your check-in QR code');
        $this->get(route('reservations.qr', $booking))->assertStatus(409);
    }

    public function test_staff_login_routes_to_scanner_and_registration_cannot_assign_staff_role(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->post(route('login.post'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('check-in.index'));
        $this->get(route('login'))->assertRedirect(route('check-in.index'));
        $this->get(route('registration'))->assertRedirect(route('check-in.index'));
        $this->get(route('logout'));
        $this->post(route('registration.post'), [
            'name' => 'New guest', 'email' => 'no-staff@example.test', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'staff',
        ])->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['email' => 'no-staff@example.test', 'role' => 'guest']);
    }

    public function test_unpaid_customers_have_no_qr_but_confirmed_future_arrivals_can_download_theirs(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
        $guest = User::factory()->create();
        $unpaid = $this->booking($guest, ['status' => 'pending', 'payment_status' => 'unpaid']);
        $future = $this->booking($guest, ['check_in' => '2026-11-02', 'check_out' => '2026-11-02', 'reference_code' => 'VNR-FUTURE']);

        $this->actingAs($guest)->get(route('reservations.show', $unpaid))->assertOk()->assertDontSee('Your check-in QR code');
        $this->get(route('reservations.qr', $unpaid))->assertStatus(409);
        $this->get(route('reservations.qr', $future))->assertOk()->assertSee('<svg', false);
    }

    private function booking(User $guest, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $guest->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => '2026-11-01', 'check_out' => '2026-11-01', 'guest_count' => 2,
            'total_price' => 1000, 'reservation_fee' => 200, 'balance' => 0,
            'status' => 'confirmed', 'payment_status' => 'paid', 'reference_code' => 'VNR-QR-TEST',
        ], $attributes));
    }
}
