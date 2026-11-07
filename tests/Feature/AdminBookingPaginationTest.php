<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_navigate_all_bookings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bookings = collect();

        for ($index = 1; $index <= 7; $index++) {
            $guest = User::factory()->create(['name' => 'Pagination Guest '.$index]);
            $bookings->push(Booking::create([
                'user_id' => $guest->id,
                'reservation_type' => 'public',
                'stay_type' => 'day_tour',
                'check_in' => '2026-11-01',
                'check_out' => '2026-11-01',
                'guest_count' => 1,
                'total_price' => 1000,
                'reservation_fee' => 200,
                'balance' => 800,
            ]));
        }

        $first = $this->actingAs($admin)->get(route('admin.bookings.index'));
        $first->assertOk()
            ->assertSee('Pagination Guest 7')
            ->assertDontSee('Pagination Guest 1')
            ->assertSee('aria-label="Previous page"', false)
            ->assertSee('rel="next"', false);
        $this->assertStringContainsString('Showing 1 to 5 of 7 results', preg_replace('/\s+/', ' ', strip_tags($first->getContent())));

        $second = $this->get(route('admin.bookings.index', ['page' => 2]));
        $second->assertOk()
            ->assertSee('Pagination Guest 1')
            ->assertDontSee('Pagination Guest 7')
            ->assertSee('rel="prev"', false)
            ->assertDontSee('rel="next"', false);
        $this->assertStringContainsString('Showing 6 to 7 of 7 results', preg_replace('/\s+/', ' ', strip_tags($second->getContent())));

        $ids = $first->viewData('recentBooking')->pluck('id')
            ->merge($second->viewData('recentBooking')->pluck('id'));
        $this->assertSame($bookings->pluck('id')->reverse()->values()->all(), $ids->all());
    }

    public function test_empty_bookings_show_zero_results_without_page_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('No recent bookings')
            ->assertDontSee('aria-label="Booking pagination"', false);
        $this->assertStringContainsString('Showing 0 to 0 of 0 results', preg_replace('/\s+/', ' ', strip_tags($response->getContent())));
    }
}
