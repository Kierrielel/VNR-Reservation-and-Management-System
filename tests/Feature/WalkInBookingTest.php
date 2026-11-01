<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WalkInBookingTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['admin'])]
    #[TestWith(['staff'])]
    public function test_staff_can_create_pay_and_check_in_a_same_day_walk_in_without_an_account(string $role): void
    {
        $operator = User::factory()->create(['role' => $role]);
        $this->actingAs($operator)->get(route('walk-ins.create'))->assertOk()
            ->assertSee('name="guest_name"', false)->assertViewHas('earliestBookingDate', today()->toDateString());
        $this->post(route('walk-ins.store'), $this->payload())->assertRedirect();
        $booking = Booking::sole();

        $this->assertNull($booking->user_id);
        $this->assertSame($operator->id, $booking->booked_by);
        $this->assertSame('Walk-in Visitor', $booking->guest_name);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->get(route('walk-ins.show', $booking))->assertOk()->assertSee('Walk-in Visitor')->assertSee('09171234567');
        $this->get(route('walk-ins.qr', $booking))->assertStatus(409);
        $this->post(route('walk-ins.payment', $booking), ['payment_received' => 1])
            ->assertRedirect(route('walk-ins.show', $booking));
        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('0.00', $booking->balance);
        $this->assertSame($booking->total_price, $booking->payments()->sole()->amount);
        $this->assertNotNull($booking->payments()->sole()->paid_at);
        $this->get(route('walk-ins.qr', $booking))->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $this->post(route('walk-ins.check-in', $booking))->assertRedirect(route('walk-ins.show', $booking));
        $this->assertSame($operator->id, $booking->fresh()->checked_in_by);
        $this->postJson(route('walk-ins.check-in', $booking))->assertUnprocessable();
        $this->postJson(route('walk-ins.payment', $booking), ['payment_received' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 1);
        $summary = app(ReportingService::class)->summary(today()->toDateString(), today()->toDateString());
        $this->assertSame((float) $booking->total_price, $summary['collected']);
        $this->assertSame(1, $summary['customers']);
    }

    public function test_customers_cannot_access_staff_walk_in_endpoints_or_other_guests_qr(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('walk-ins.store'), $this->payload())->assertRedirect();
        $booking = Booking::sole();
        $guest = User::factory()->create(['role' => 'guest']);
        $this->actingAs($guest)->get(route('walk-ins.create'))->assertForbidden();
        $this->post(route('walk-ins.store'), [])->assertForbidden();
        $this->get(route('walk-ins.show', $booking))->assertForbidden();
        $this->post(route('walk-ins.payment', $booking), ['payment_received' => 1])->assertForbidden();
        $this->post(route('walk-ins.check-in', $booking))->assertForbidden();
        $this->get(route('walk-ins.qr', $booking))->assertForbidden();
        $this->get(route('reservations.qr', $booking))->assertForbidden();
    }

    public function test_walk_ins_require_guest_details_and_explicit_cash_confirmation(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $payload = $this->payload();
        unset($payload['guest_name'], $payload['guest_contact']);
        $this->actingAs($staff)->postJson(route('walk-ins.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['guest_name', 'guest_contact']);
        $this->post(route('walk-ins.store'), $this->payload())->assertRedirect();
        $booking = Booking::sole();
        $this->postJson(route('walk-ins.payment', $booking), ['payment_received' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_received');
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 0);
    }

    #[TestWith(['cancelled', false])]
    #[TestWith(['expired', false])]
    #[TestWith(['pending', true])]
    public function test_cash_payment_cannot_reopen_closed_or_overdue_walk_ins(string $status, bool $overdue): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->post(route('walk-ins.store'), $this->payload())->assertRedirect();
        $booking = Booking::sole();
        $booking->update(['status' => $status, 'expires_at' => $overdue ? now()->subMinute() : now()->addMinutes(30)]);
        $this->postJson(route('walk-ins.payment', $booking), ['payment_received' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_received');
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($status, $booking->fresh()->status);
    }

    public function test_customer_cannot_submit_walk_in_fields_through_the_customer_endpoint(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $payload = $this->payload();
        $payload['check_in'] = today()->addDay()->toDateString();
        $payload['booked_by'] = $guest->id;
        $payload['payment_status'] = 'paid';
        $this->actingAs($guest)->post(route('reservations.store'), $payload)->assertRedirect();
        $booking = Booking::sole();
        $this->assertSame($guest->id, $booking->user_id);
        $this->assertNull($booking->guest_name);
        $this->assertNull($booking->booked_by);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->get(route('reservations.show', $booking))->assertOk();
    }

    public function test_admin_search_and_csv_show_walk_in_guest_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('walk-ins.store'), $this->payload())->assertRedirect();
        $booking = Booking::sole();
        $this->get(route('admin.bookings.index', ['search' => '09171234567']))->assertOk()->assertSee('Walk-in Visitor');
        $this->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Walk-in Visitor')->assertSee('09171234567');
        $this->post(route('walk-ins.payment', $booking), ['payment_received' => 1])->assertRedirect();
        $response = $this->get(route('admin.reports.export', [
            'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(),
        ]))->assertOk();
        $this->assertStringContainsString('Walk-in Visitor', $response->streamedContent());
    }

    private function payload(): array
    {
        $cottage = Cottage::firstOrCreate(['name' => 'Walk-in cottage'], [
            'type' => 'cottage', 'capacity' => 5, 'price_day' => 1000,
            'price_overnight' => 1500, 'status' => 'available',
        ]);

        return [
            'reservation_type' => 'public', 'stay_type' => 'day_tour', 'check_in' => today()->toDateString(),
            'guest_count' => 2, 'cottage_ids' => [$cottage->id], 'guest_name' => 'Walk-in Visitor',
            'guest_contact' => '09171234567', 'guest_email' => 'walkin@example.test',
        ];
    }

    public function test_walk_in_management_cannot_be_used_to_pay_or_check_in_account_bookings(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $payload = $this->payload();
        $payload['check_in'] = today()->addDay()->toDateString();
        $this->actingAs($guest)->post(route('reservations.store'), $payload)->assertRedirect();
        $booking = Booking::sole();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('walk-ins.show', $booking))->assertNotFound();
        $this->post(route('walk-ins.payment', $booking), ['payment_received' => 1])->assertNotFound();
        $this->post(route('walk-ins.check-in', $booking))->assertNotFound();
        $this->get(route('walk-ins.qr', $booking))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unauthenticated_visitors_cannot_open_or_create_walk_ins(): void
    {
        $this->get(route('walk-ins.create'))->assertRedirect(route('login'));
        $this->postJson(route('walk-ins.store'), [])->assertUnauthorized();
        $this->assertDatabaseCount('bookings', 0);
    }
}
