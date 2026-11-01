<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\EventPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BookingDateAccessTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['admin', 0])]
    #[TestWith(['staff', 0])]
    #[TestWith(['guest', 1])]
    public function test_form_and_submission_use_the_same_earliest_date(string $role, int $days): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $user = User::factory()->create(['role' => $role]);
        $date = today()->addDays($days)->toDateString();
        $cottage = $this->cottage();

        $this->actingAs($user)->get(route('book'))->assertOk()
            ->assertViewHas('earliestBookingDate', $date);
        $this->getJson(route('reservations.availability', ['check_in' => $date, 'stay_type' => 'day_tour']))
            ->assertOk()->assertJsonPath('0.id', $cottage->id);
        $this->post(route('reservations.store'), $this->payload($cottage, $date))->assertRedirect();

        $booking = Booking::sole();
        $this->assertSame($date, $booking->check_in->toDateString());
        $this->assertSame($user->id, $booking->user_id);
    }

    #[TestWith(['guest', 0])]
    #[TestWith(['admin', -1])]
    #[TestWith(['staff', -1])]
    public function test_earlier_dates_are_rejected_even_if_the_form_is_bypassed(string $role, int $days): void
    {
        $user = User::factory()->create(['role' => $role]);
        $cottage = $this->cottage();
        $date = today()->addDays($days)->toDateString();
        $this->actingAs($user)->getJson(route('reservations.availability', ['check_in' => $date, 'stay_type' => 'day_tour']))
            ->assertUnprocessable()->assertJsonValidationErrors('check_in');
        $this->postJson(route('reservations.store'), $this->payload($cottage, $date) + ['role' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors('check_in');
        $this->assertDatabaseCount('bookings', 0);
    }

    #[TestWith(['admin'])]
    #[TestWith(['staff'])]
    public function test_same_day_calendar_and_submission_respect_existing_bookings(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $cottage = $this->cottage();
        $date = today()->toDateString();
        $this->actingAs($user)->post(route('reservations.store'), $this->payload($cottage, $date))->assertRedirect();
        $this->getJson(route('reservations.disabled-dates', ['reservation_type' => 'public', 'stay_type' => 'day_tour']))
            ->assertOk()->assertExactJson([$date]);
        $this->postJson(route('reservations.store'), $this->payload($cottage, $date))
            ->assertUnprocessable()->assertJsonValidationErrors('cottage_ids');
        $this->assertDatabaseCount('bookings', 1);
    }

    private function cottage(): Cottage
    {
        return Cottage::create([
            'name' => 'Walk-in cottage', 'type' => 'cottage', 'capacity' => 5,
            'price_day' => 1000, 'price_overnight' => 1500, 'status' => 'available',
        ]);
    }

    #[TestWith(['admin'])]
    #[TestWith(['staff'])]
    public function test_staff_event_calendar_blocks_an_existing_same_day_event(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $package = EventPackage::create(['name' => 'Walk-in event', 'price' => 10000, 'hours' => 6, 'status' => 'available']);
        $payload = [
            'reservation_type' => 'event', 'event_package_id' => $package->id,
            'check_in' => today()->toDateString(), 'guest_count' => 20,
        ];
        $this->actingAs($user)->post(route('reservations.store'), $payload)->assertRedirect();
        $this->getJson(route('reservations.disabled-dates', ['reservation_type' => 'event']))
            ->assertOk()->assertExactJson([today()->toDateString()]);
        $this->postJson(route('reservations.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('check_in');
        $this->assertDatabaseCount('bookings', 1);
    }

    private function payload(Cottage $cottage, string $date): array
    {
        return [
            'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'cottage_ids' => [$cottage->id], 'check_in' => $date, 'guest_count' => 2,
        ];
    }
}
