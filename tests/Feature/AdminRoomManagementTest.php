<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminRoomManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_type_and_status_filters_are_preserved_through_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        for ($index = 1; $index <= 9; $index++) {
            Cottage::create($this->attributes(['name' => 'Garden Room '.$index, 'type' => 'room']));
        }
        Cottage::create($this->attributes(['name' => 'Garden Cottage']));
        Cottage::create($this->attributes(['name' => 'Garden Closed', 'type' => 'room', 'status' => 'unavailable']));
        $query = ['search' => 'Garden', 'type' => 'room', 'status' => 'available'];

        $response = $this->actingAs($admin)->get(route('admin.rooms.index', $query));

        $response->assertOk()->assertSee('Garden Room 1')->assertDontSee('Garden Cottage')->assertDontSee('Garden Closed');
        $this->assertSame(9, $response->viewData('cottages')->total());
        $this->get($response->viewData('cottages')->nextPageUrl())->assertOk()->assertSee('Garden Room 9')
            ->assertDontSee('Garden Cottage')->assertDontSee('Garden Closed');
    }

    public function test_admin_can_create_and_edit_prices_without_changing_existing_booking_totals(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $attributes = $this->attributes(['name' => 'New Suite', 'type' => 'room', 'price_overnight' => 2000]);

        $this->actingAs($admin)->get(route('admin.rooms.create'))->assertOk()->assertSee('Add accommodation');
        $this->post(route('admin.rooms.store'), $attributes + ['image' => 'untrusted-path', 'id' => 999])
            ->assertRedirect();

        $cottage = Cottage::sole();
        $this->assertSame('New Suite', $cottage->name);
        $this->assertNull($cottage->image);
        $booking = $this->booking($cottage, 'confirmed');
        $this->get(route('admin.rooms.edit', $cottage))->assertOk()->assertSee('New Suite');
        $this->put(route('admin.rooms.update', $cottage), array_merge($attributes, [
            'price_day' => 1500, 'price_overnight' => null, 'capacity' => 8,
        ]))->assertRedirect(route('admin.rooms.edit', $cottage));

        $this->assertDatabaseHas('cottages', ['id' => $cottage->id, 'price_day' => 1500, 'price_overnight' => null, 'capacity' => 8]);
        $this->assertSame('1000.00', $booking->fresh()->total_price);
    }

    #[DataProvider('restrictedRequests')]
    public function test_room_management_requires_admin_access(string $method, string $action, bool $authenticated): void
    {
        $guest = User::factory()->create();
        $cottage = Cottage::create($this->attributes());
        if ($authenticated) {
            $this->actingAs($guest);
        }

        $response = $this->json($method, route('admin.rooms.'.$action, ['cottage' => $cottage]), $this->attributes(['status' => 'unavailable']));

        $authenticated ? $response->assertForbidden() : $response->assertUnauthorized();
        $this->assertDatabaseCount('cottages', 1);
        $this->assertSame('available', $cottage->fresh()->status);
    }

    public static function restrictedRequests(): array
    {
        $cases = [];
        foreach ([false, true] as $authenticated) {
            foreach ([['GET', 'index'], ['GET', 'create'], ['GET', 'edit'], ['POST', 'store'], ['PUT', 'update'], ['DELETE', 'destroy']] as [$method, $action]) {
                $cases[($authenticated ? 'customer ' : 'visitor ').$action] = [$method, $action, $authenticated];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidAttributes')]
    public function test_invalid_room_data_returns_422_without_creating_records(string $field, mixed $value): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson(route('admin.rooms.store'), $this->attributes([$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('cottages', 0);
    }

    public static function invalidAttributes(): array
    {
        return [
            ['name', ''], ['type', 'invalid'], ['capacity', 0], ['capacity', 1.5],
            ['price_day', -1], ['price_overnight', -1], ['price_day', '10.123'],
            ['status', 'invalid'],
        ];
    }

    public function test_duplicate_name_is_rejected_but_same_name_can_be_kept_on_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cottage = Cottage::create($this->attributes());

        $this->actingAs($admin)->postJson(route('admin.rooms.store'), $this->attributes())
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->put(route('admin.rooms.update', $cottage), $this->attributes())->assertRedirect();
        $this->assertDatabaseCount('cottages', 1);
    }

    public function test_operational_status_and_overnight_rate_control_new_availability(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $cottage = Cottage::create($this->attributes(['price_overnight' => 2000]));
        $query = ['check_in' => today()->addDay()->toDateString(), 'stay_type' => 'overnight'];

        $this->actingAs($admin)->put(route('admin.rooms.update', $cottage), $this->attributes(['status' => 'unavailable', 'price_overnight' => 2000]))->assertRedirect();
        $this->getJson(route('reservations.availability', $query))->assertOk()->assertJsonCount(0);
        $this->put(route('admin.rooms.update', $cottage), $this->attributes(['price_overnight' => 2000]))->assertRedirect();
        $this->getJson(route('reservations.availability', $query))->assertOk()->assertJsonCount(1);
        $this->put(route('admin.rooms.update', $cottage), $this->attributes())->assertRedirect();
        $this->getJson(route('reservations.availability', $query))->assertOk()->assertJsonCount(0);
    }

    #[DataProvider('bookingStatuses')]
    public function test_accommodation_with_any_booking_history_cannot_be_deleted(string $status): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $cottage = Cottage::create($this->attributes());
        $booking = $this->booking($cottage, $status);

        $this->actingAs($admin)->deleteJson(route('admin.rooms.destroy', $cottage))
            ->assertUnprocessable()->assertJsonValidationErrors('cottage');

        $this->assertModelExists($cottage);
        $this->assertModelExists($booking);
        $this->assertDatabaseCount('booking_cottage', 1);
        $this->get(route('admin.rooms.index'))->assertOk()->assertSee('Booking history kept');
    }

    public static function bookingStatuses(): array
    {
        return [['pending'], ['confirmed'], ['cancelled'], ['completed']];
    }

    public function test_unused_accommodation_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cottage = Cottage::create($this->attributes());

        $this->actingAs($admin)->delete(route('admin.rooms.destroy', $cottage))->assertRedirect(route('admin.rooms.index'));

        $this->assertModelMissing($cottage);
    }

    public function test_guest_content_is_escaped_and_legacy_preview_requires_authentication(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Cottage::create($this->attributes(['name' => '<script>alert(1)</script>']));

        $this->get('/admin/include/rooms')->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('admin.rooms.index'))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/admin/include/rooms')->assertRedirect(route('admin.rooms.index'));
    }

    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Cottage', 'type' => 'cottage', 'capacity' => 5,
            'price_day' => 1000, 'price_overnight' => null, 'status' => 'available',
        ], $overrides);
    }

    private function booking(Cottage $cottage, string $status): Booking
    {
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDay()->toDateString(),
            'guest_count' => 2, 'total_price' => 1000, 'reservation_fee' => 200, 'balance' => 800,
            'status' => $status, 'payment_status' => 'paid',
        ]);
        $booking->cottages()->attach($cottage);

        return $booking;
    }
}
