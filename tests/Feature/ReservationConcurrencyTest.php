<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\EventPackage;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationConcurrencyTest extends TestCase
{
    #[DataProvider('bookingScenarios')]
    public function test_parallel_reservations_only_reject_conflicting_bookings(string $scenario, array $expectedStatuses): void
    {
        $this->freezeTime();
        $database = tempnam(sys_get_temp_dir(), 'vnr-concurrency-');
        $processes = [];

        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
            $guests = User::factory()->count(2)->create();
            if ($scenario === 'delete race') {
                $guests[1]->update(['role' => 'admin']);
            } elseif ($scenario === 'check-in race') {
                $guests->each(fn (User $user) => $user->update(['role' => 'staff']));
            }
            $inputs = [];

            foreach ($this->payloads($scenario) as $index => $payload) {
                $input = new InputStream;
                $process = new Process([
                    PHP_BINARY, base_path('tests/Support/reservation-worker.php'),
                    (string) $guests[$index]->id, json_encode($payload, JSON_THROW_ON_ERROR),
                ], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
                    'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
                    'APP_KEY' => config('app.key'),
                ]);
                $process->setTimeout(20);
                $process->setInput($input);
                $process->start();
                $processes[] = $process;
                $inputs[] = $input;
            }

            foreach ($processes as $process) {
                $this->assertTrue(str_contains($process->getOutput(), 'READY')
                    || $process->waitUntil(fn (): bool => str_contains($process->getOutput(), 'READY')),
                    $process->getErrorOutput());
            }
            foreach ($inputs as $input) {
                $input->write("GO\n");
                $input->close();
            }
            foreach ($processes as $process) {
                $process->isRunning();
            }

            $statuses = [];
            $results = [];
            foreach ($processes as $index => $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $result = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
                $statuses[] = $result['status'];
                $results[] = $result;
                if ($result['status'] === 422) {
                    if ($scenario === 'check-in race') {
                        $this->assertSame(['token'], $result['errors']);
                    } elseif ($scenario === 'delete race') {
                        $this->assertContains($result['errors'], $index === 1 ? [['cottage']] : [['cottage_ids'], ['cottage_ids.0']]);
                    } else {
                        $this->assertSame([$scenario === 'event conflict' ? 'check_in' : 'cottage_ids'], $result['errors']);
                    }
                }
            }

            sort($statuses);
            $this->assertSame($expectedStatuses, $statuses);
            $expectedBookings = match ($scenario) {
                'check-in race' => 1,
                'delete race' => $results[0]['status'] === 302 ? 1 : 0,
                default => count(array_filter($expectedStatuses, fn (int $status): bool => $status === 302)),
            };
            $this->assertDatabaseCount('bookings', $expectedBookings);
            if ($scenario === 'delete race') {
                $this->assertDatabaseCount('cottages', $expectedBookings === 1 ? 2 : 1);
            } elseif ($scenario === 'check-in race') {
                $arrival = Booking::sole();
                $this->assertNotNull($arrival->checked_in_at);
                $this->assertContains($arrival->checked_in_by, $guests->pluck('id')->all());
            }
            $expectedCottages = match (true) {
                str_starts_with($scenario, 'event') => 0,
                $scenario === 'reversed cottages' => 2,
                default => Booking::count(),
            };
            $this->assertDatabaseCount('booking_cottage', $expectedCottages);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::purge('sqlite');
            if (is_file($database)) {
                unlink($database);
            }
        }
    }

    public static function bookingScenarios(): array
    {
        return [
            'same cottage and date' => ['cottage conflict', [302, 422]],
            'overnight overlap' => ['overnight conflict', [302, 422]],
            'same cottages selected in reverse order' => ['reversed cottages', [302, 422]],
            'different event packages on same date' => ['event conflict', [302, 422]],
            'different cottages' => ['different cottages', [302, 302]],
            'different event dates' => ['event different dates', [302, 302]],
            'deletion racing with booking' => ['delete race', [302, 422]],
            'two staff scan the same QR' => ['check-in race', [200, 422]],
        ];
    }

    private function payloads(string $scenario): array
    {
        $date = today()->addDays(2)->toDateString();
        if ($scenario === 'check-in race') {
            $cottage = Cottage::create(['name' => 'QR cottage', 'type' => 'cottage', 'capacity' => 5, 'price_day' => 1000, 'status' => 'available']);
            $booking = Booking::create([
                'user_id' => User::factory()->create()->id, 'reservation_type' => 'public', 'stay_type' => 'day_tour',
                'check_in' => today()->toDateString(), 'check_out' => today()->toDateString(),
                'guest_count' => 1, 'total_price' => 1000, 'reservation_fee' => 200, 'balance' => 0,
                'status' => 'confirmed', 'payment_status' => 'paid',
            ]);
            $booking->cottages()->attach($cottage);
            $token = app(CheckInService::class)->token($booking);

            return [['operation' => 'check-in', 'token' => $token], ['operation' => 'check-in', 'token' => $token]];
        }
        if (str_starts_with($scenario, 'event')) {
            $packages = collect(['Silver', 'Gold'])->map(fn (string $name): EventPackage => EventPackage::create([
                'name' => $name, 'price' => 30000, 'hours' => 6, 'status' => 'available',
            ]));

            return $packages->map(fn (EventPackage $package, int $index): array => [
                'reservation_type' => 'event', 'event_package_id' => $package->id,
                'check_in' => $scenario === 'event different dates' && $index === 1
                    ? today()->addDays(3)->toDateString() : $date,
                'guest_count' => 20,
            ])->all();
        }

        $cottages = collect(['First cottage', 'Second cottage'])->map(fn (string $name): Cottage => Cottage::create([
            'name' => $name, 'type' => 'cottage', 'capacity' => 5, 'price_day' => 1000,
            'price_overnight' => 1500, 'status' => 'available',
        ]));
        $first = [
            'reservation_type' => 'public', 'stay_type' => $scenario === 'overnight conflict' ? 'overnight' : 'day_tour',
            'check_in' => $date, 'cottage_ids' => [$cottages[0]->id], 'guest_count' => 2,
        ];
        $second = $first;
        $second['stay_type'] = 'day_tour';
        if ($scenario === 'delete race') {
            $second = ['operation' => 'delete-cottage', 'cottage_id' => $cottages[0]->id];
        } elseif ($scenario === 'different cottages') {
            $second['cottage_ids'] = [$cottages[1]->id];
        } elseif ($scenario === 'reversed cottages') {
            $first['cottage_ids'] = [$cottages[0]->id, $cottages[1]->id];
            $second['cottage_ids'] = [$cottages[1]->id, $cottages[0]->id];
        } elseif ($scenario === 'overnight conflict') {
            $second['check_in'] = today()->addDays(3)->toDateString();
        }

        return [$first, $second];
    }
}
