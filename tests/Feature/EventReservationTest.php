<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\EventPackage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_admin_forms_list_only_available_packages(): void
    {
        $package = $this->package();
        $this->package(['name' => 'Unavailable package', 'status' => 'unavailable']);
        Setting::create(['key' => 'reservation_fee_percent', 'value' => '25']);

        $this->get(route('book'))->assertOk()
            ->assertSee('name="event_package_id"', false)
            ->assertSee($package->name)->assertDontSee('Unavailable package')
            ->assertSee('₱30,000.00')->assertSee('Reservation fee (25%)')
            ->assertSee('Remaining balance')->assertViewHas('reservationFeePercent', 25.0);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.book'))->assertOk()
            ->assertSee($package->name)->assertDontSee('Unavailable package')
            ->assertViewHas('reservationFeePercent', 25.0);
    }

    public function test_form_handles_no_available_packages(): void
    {
        $this->get(route('book'))->assertOk()
            ->assertSee('No event packages are currently available.');
    }

    public function test_event_reservation_uses_database_price_and_configured_fee(): void
    {
        $guest = User::factory()->create();
        $package = $this->package();
        Setting::create(['key' => 'reservation_fee_percent', 'value' => '25']);

        $response = $this->actingAs($guest)->post(route('reservations.store'), $this->payload($package) + [
            'total_price' => 1, 'reservation_fee' => 1, 'balance' => 0,
        ]);

        $booking = Booking::sole();
        $response->assertRedirect(route('reservations.show', $booking));
        $this->assertSame($package->id, $booking->event_package_id);
        $this->assertSame('30000.00', $booking->total_price);
        $this->assertSame('7500.00', $booking->reservation_fee);
        $this->assertSame('22500.00', $booking->balance);
        $this->assertSame('pending', $booking->status);
        $this->assertNull($booking->stay_type);
        $this->assertTrue($booking->check_in->equalTo($booking->check_out));
        $this->assertDatabaseCount('booking_cottage', 0);
        $this->get(route('reservations.show', $booking))->assertOk()->assertSee($package->name);
    }

    public function test_unavailable_or_missing_packages_cannot_be_reserved(): void
    {
        $guest = User::factory()->create();
        $package = $this->package(['status' => 'unavailable']);
        $this->actingAs($guest)->postJson(route('reservations.store'), $this->payload($package))
            ->assertUnprocessable()->assertJsonValidationErrors('event_package_id');

        $payload = $this->payload($package);
        unset($payload['event_package_id']);
        $this->postJson(route('reservations.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('event_package_id');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_existing_event_blocks_date_until_cancelled(): void
    {
        $guest = User::factory()->create();
        $package = $this->package();
        $payload = $this->payload($package);
        $this->actingAs($guest)->post(route('reservations.store'), $payload)->assertRedirect();
        $this->postJson(route('reservations.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('check_in');
        $this->assertDatabaseCount('bookings', 1);

        Booking::sole()->update(['status' => 'cancelled']);
        $this->post(route('reservations.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_validation_errors_keep_event_form_values(): void
    {
        $guest = User::factory()->create();
        $package = $this->package();
        $payload = $this->payload($package);
        $payload['guest_count'] = 0;
        $this->actingAs($guest)->from(route('book'))->post(route('reservations.store'), $payload)
            ->assertRedirect(route('book'))->assertSessionHasErrors('guest_count');
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('book'))->assertOk()->assertSee('role="alert"', false)
            ->assertSee('value="event" selected', false)
            ->assertSee('value="'.$payload['check_in'].'"', false);
        $this->assertDatabaseCount('bookings', 0);
    }

    private function package(array $attributes = []): EventPackage
    {
        return EventPackage::create(array_merge([
            'name' => 'Silver package', 'price' => 30000, 'hours' => 6, 'status' => 'available',
        ], $attributes));
    }

    private function payload(EventPackage $package): array
    {
        return [
            'reservation_type' => 'event', 'event_package_id' => $package->id,
            'check_in' => today()->addDay()->toDateString(), 'guest_count' => 50,
        ];
    }
}
