<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReservationCapacityTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['guest', 'day_tour'])]
    #[TestWith(['guest', 'overnight'])]
    #[TestWith(['admin', 'day_tour'])]
    #[TestWith(['admin', 'overnight'])]
    #[TestWith(['staff', 'day_tour'])]
    #[TestWith(['staff', 'overnight'])]
    public function test_two_accommodations_allow_guests_up_to_their_combined_capacity(string $role, string $stayType): void
    {
        $first = $this->cottage('First cottage', 5);
        $second = $this->cottage('Second cottage', 3);
        $payload = $this->payload($role, [$first->id, $second->id], 8);
        $payload['stay_type'] = $stayType;
        $this->submit($role, $payload)->assertRedirect();

        $booking = Booking::sole();
        $this->assertSame(8, $booking->guest_count);
        $this->assertSame(2, $booking->cottages()->count());
        $this->assertSame($stayType === 'day_tour' ? '3200.00' : '4600.00', $booking->total_price);
    }

    #[TestWith(['guest', 1])]
    #[TestWith(['guest', 2])]
    #[TestWith(['admin', 1])]
    #[TestWith(['admin', 2])]
    #[TestWith(['staff', 1])]
    #[TestWith(['staff', 2])]
    public function test_guests_cannot_exceed_capacity_even_with_a_forged_capacity_value(string $role, int $count): void
    {
        $ids = [$this->cottage('First cottage', 5)->id];
        if ($count === 2) {
            $ids[] = $this->cottage('Second cottage', 3)->id;
        }
        $capacity = $count === 1 ? 5 : 8;
        $payload = $this->payload($role, $ids, $capacity + 1) + ['capacity' => 999, 'total_price' => 1];
        $this->submit($role, $payload)->assertUnprocessable()->assertJsonValidationErrors('guest_count')
            ->assertJsonPath('errors.guest_count.0', "The selected accommodations can hold a maximum of {$capacity} guests. Select another accommodation (up to 2 total) or reduce the number of guests.");
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_cottage', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    #[TestWith(['guest'])]
    #[TestWith(['admin'])]
    #[TestWith(['staff'])]
    public function test_more_than_two_cottages_are_rejected_even_when_total_capacity_is_enough(string $role): void
    {
        $ids = [
            $this->cottage('First cottage', 5)->id,
            $this->cottage('Second cottage', 5)->id,
            $this->cottage('Third cottage', 5)->id,
        ];
        $this->submit($role, $this->payload($role, $ids, 1))
            ->assertUnprocessable()->assertJsonValidationErrors('cottage_ids');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_duplicate_cottage_ids_cannot_inflate_capacity(): void
    {
        $id = $this->cottage('First cottage', 5)->id;
        $this->submit('guest', $this->payload('guest', [$id, $id], 8))
            ->assertUnprocessable()->assertJsonValidationErrors('cottage_ids.0');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_capacity_is_read_again_when_booking_is_submitted(): void
    {
        $cottage = $this->cottage('First cottage', 5);
        $guest = User::factory()->create(['role' => 'guest']);
        $payload = $this->payload('guest', [$cottage->id], 5);
        $this->actingAs($guest)->getJson(route('reservations.availability', [
            'check_in' => $payload['check_in'], 'stay_type' => 'day_tour',
        ]))->assertOk()->assertJsonPath('0.capacity', 5);
        $cottage->update(['capacity' => 4]);
        $this->postJson(route('reservations.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('guest_count');
        $this->assertDatabaseCount('bookings', 0);
    }

    private function submit(string $role, array $payload): TestResponse
    {
        $user = User::factory()->create(['role' => $role]);

        return $this->actingAs($user)->postJson(route($role === 'guest' ? 'reservations.store' : 'walk-ins.store'), $payload);
    }

    private function cottage(string $name, int $capacity): Cottage
    {
        return Cottage::create([
            'name' => $name, 'type' => 'cottage', 'capacity' => $capacity,
            'price_day' => 1000, 'price_overnight' => 1500, 'status' => 'available',
        ]);
    }

    private function payload(string $role, array $ids, int $guests): array
    {
        return [
            'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'cottage_ids' => $ids, 'guest_count' => $guests,
            'check_in' => today()->addDays($role === 'guest' ? 1 : 0)->toDateString(),
            'guest_name' => 'Walk-in Guest', 'guest_contact' => '09171234567',
        ];
    }
}
