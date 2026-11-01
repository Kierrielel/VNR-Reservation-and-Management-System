<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentConfirmationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_collections_and_booked_units_without_treating_maintenance_as_occupancy(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create(), ['check_in' => '2026-11-15', 'check_out' => '2026-11-15']);
        $booked = $this->cottage('Booked');
        $booking->cottages()->attach($booked);
        $this->cottage('Free');
        $this->cottage('Maintenance', 'unavailable');
        $this->payment($booking, 200, '2026-11-15 10:00:00');
        $this->payment($booking, 800, '2026-11-15 11:00:00', 'pending');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertViewHas('totalRevenue', 200.0)
            ->assertViewHas('availableCottages', 1)->assertViewHas('occupancyRate', 33.33)
            ->assertViewHas('occupancyChart', ['labels' => ['Booked', 'Available', 'Unavailable'], 'values' => [1, 1, 1]])
            ->assertSee('id="occupancyChart"', false)->assertSee('data-values="[1,1,1]"', false);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 200.0], $response->viewData('chart')['values']);
    }

    public function test_occupancy_chart_handles_no_accommodation_without_dividing_by_zero(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertSee('No rooms or cottages configured.')
            ->assertSee('0.00%')->assertViewHas('occupancyRate', 0);
        $this->assertSame([0, 0, 0], $response->viewData('occupancyChart')['values']);
    }

    public function test_occupancy_chart_counts_a_booked_unit_once_when_multiple_bookings_overlap(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create();
        $cottage = $this->cottage('Booked unit');
        $this->booking($guest)->cottages()->attach($cottage);
        $this->booking($guest)->cottages()->attach($cottage);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertSee('100.00%')->assertViewHas('occupancyRate', 100.0);
        $this->assertSame([1, 0, 0], $response->viewData('occupancyChart')['values']);
    }

    public function test_report_uses_paid_dates_and_keeps_cancelled_payments_without_inflating_guest_counts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create();
        $booking = $this->booking($guest);
        $this->booking($guest);
        $cancelled = $this->booking(User::factory()->create(), ['status' => 'cancelled']);
        $this->payment($booking, 200, '2026-11-01 00:00:00');
        $this->payment($booking, 800, '2026-11-03 23:59:59');
        $this->payment($cancelled, 150, '2026-11-03 12:00:00');
        $this->payment($booking, 5000, '2026-11-02 12:00:00', 'pending');
        $this->payment($booking, 5000, '2026-11-02 12:00:00', 'failed');
        $this->payment($booking, 5000, '2026-10-31 23:59:59');

        $response = $this->actingAs($admin)->get(route('admin.reports.index', $this->period()));

        $response->assertOk()->assertSee('1,150.00')->assertSee('Download CSV (Excel)');
        $this->assertSame(['collected' => 1150.0, 'customers' => 1, 'bookings' => 2, 'cancelled' => 1], $response->viewData('summary'));
        $this->assertSame(['labels' => ['2026-11-01', '2026-11-02', '2026-11-03'], 'values' => [200.0, 0.0, 950.0]], $response->viewData('chart'));
        $this->assertSame(1, substr_count($response->getContent(), 'cdn.jsdelivr.net/npm/chart.js'));
    }

    public function test_empty_report_defaults_to_current_month_and_zero_fills_the_chart(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertOk()->assertViewHas('period', $this->period());
        $this->assertSame([0.0, 0.0, 0.0], $response->viewData('chart')['values']);
        $this->assertSame(0.0, $response->viewData('summary')['collected']);
    }

    #[DataProvider('restrictedReports')]
    public function test_reporting_endpoints_require_admin_access(string $route, bool $authenticated): void
    {
        if ($authenticated) {
            $this->actingAs(User::factory()->create());
        }

        $response = $this->getJson(route($route, $this->period()));

        $authenticated ? $response->assertForbidden() : $response->assertUnauthorized();
    }

    public static function restrictedReports(): array
    {
        $cases = [];
        foreach ([false, true] as $authenticated) {
            foreach (['admin.dashboard', 'admin.reports.index', 'admin.reports.export', 'admin.reports.print'] as $route) {
                $cases[($authenticated ? 'customer ' : 'visitor ').$route] = [$route, $authenticated];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_periods_are_rejected_before_reporting(array $period, string $field): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson(route('admin.reports.index', $period))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidPeriods(): array
    {
        return [
            [['start_date' => 'invalid', 'end_date' => '2026-11-03'], 'start_date'],
            [['start_date' => '2026-11-03', 'end_date' => '2026-11-01'], 'end_date'],
            [['start_date' => '2025-01-01', 'end_date' => '2026-11-01'], 'end_date'],
            [['start_date' => '2026-02-31', 'end_date' => '2026-03-03'], 'start_date'],
        ];
    }

    public function test_csv_exports_only_paid_rows_and_neutralizes_spreadsheet_formulas(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create(['name' => '=HYPERLINK("https://example.test")']);
        $booking = $this->booking($guest);
        $payment = $this->payment($booking, 200, '2026-11-02 12:00:00');
        $payment->update(['reference_no' => '@untrusted-reference']);
        $this->payment($booking, 800, '2026-11-02 12:00:00', 'failed');

        $response = $this->actingAs($admin)->get(route('admin.reports.export', $this->period()));

        $response->assertOk()->assertDownload('payments-2026-11-01-to-2026-11-03.csv');
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($response->streamedContent(), 3));
        rewind($stream);
        fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');
        $this->assertSame("'".$guest->name, $row[3]);
        $this->assertSame('200.00', $row[5]);
        $this->assertSame("'@untrusted-reference", $row[7]);
        $this->assertFalse(fgetcsv($stream, escape: ''));
        fclose($stream);
    }

    public function test_print_view_uses_same_period_and_totals_without_chart_scripts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create());
        $this->payment($booking, 200, '2026-11-02 12:00:00');

        $this->actingAs($admin)->get(route('admin.reports.print', $this->period()))->assertOk()
            ->assertSee('2026-11-01 to 2026-11-03')->assertSee('200.00')->assertDontSee('chart.js');
    }

    public function test_confirmation_sets_payment_date_once_and_legacy_records_use_their_update_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(User::factory()->create());
        $pending = $this->payment($booking, 200, null, 'pending');

        app(PaymentConfirmationService::class)->confirm($pending, 'first');
        $this->assertSame('2026-11-02 12:00:00', $pending->fresh()->paid_at->format('Y-m-d H:i:s'));
        $this->travel(1)->days();
        app(PaymentConfirmationService::class)->confirm($pending, 'repeat');
        $this->assertSame('2026-11-02 12:00:00', $pending->fresh()->paid_at->format('Y-m-d H:i:s'));
        $this->payment($booking, 100, null);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', $this->period()));
        $response->assertOk();
        $this->assertSame([0.0, 200.0, 100.0], $response->viewData('chart')['values']);
    }

    private function period(): array
    {
        return ['start_date' => '2026-11-01', 'end_date' => '2026-11-03'];
    }

    private function booking(User $guest, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $guest->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
            'check_in' => '2026-11-02', 'check_out' => '2026-11-02', 'guest_count' => 1,
            'total_price' => 1000, 'reservation_fee' => 200, 'balance' => 800,
            'status' => 'confirmed', 'payment_status' => 'paid',
        ], $attributes));
    }

    private function payment(Booking $booking, int $amount, ?string $paidAt, string $status = 'paid'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id, 'method' => 'gcash', 'amount' => $amount,
            'status' => $status, 'paid_at' => $paidAt,
        ]);
    }

    private function cottage(string $name, string $status = 'available'): Cottage
    {
        return Cottage::create([
            'name' => $name, 'type' => 'cottage', 'capacity' => 5, 'price_day' => 1000, 'status' => $status,
        ]);
    }
}
