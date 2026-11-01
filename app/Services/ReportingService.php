<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public function collectedPayments(?string $start = null, ?string $end = null): Builder
    {
        return Payment::where('status', 'paid')->when($start !== null && $end !== null, function (Builder $query) use ($start, $end): void {
            $period = [$start.' 00:00:00', $end.' 23:59:59'];
            $query->where(function (Builder $query) use ($period): void {
                $query->whereBetween('paid_at', $period)
                    ->orWhere(function (Builder $legacy) use ($period): void {
                        $legacy->whereNull('paid_at')->whereBetween(DB::raw('COALESCE(updated_at, created_at)'), $period);
                    });
            });
        });
    }

    /** @return array{labels: list<string>, values: list<float>} */
    public function dailyRevenue(string $start, string $end): array
    {
        $totals = $this->collectedPayments($start, $end)
            ->selectRaw('DATE(COALESCE(paid_at, updated_at, created_at)) as payment_day, SUM(amount) as collected')
            ->groupByRaw('DATE(COALESCE(paid_at, updated_at, created_at))')
            ->pluck('collected', 'payment_day');
        $labels = [];
        $values = [];
        $lastDay = CarbonImmutable::parse($end);

        for ($day = CarbonImmutable::parse($start); $day->lte($lastDay); $day = $day->addDay()) {
            $date = $day->toDateString();
            $labels[] = $date;
            $values[] = (float) ($totals[$date] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{collected: float, customers: int, bookings: int, cancelled: int} */
    public function summary(string $start, string $end): array
    {
        $arrivals = Booking::whereDate('check_in', '>=', $start)->whereDate('check_in', '<=', $end);
        $activeArrivals = (clone $arrivals)->whereNotIn('status', ['cancelled', 'expired']);

        return [
            'collected' => (float) $this->collectedPayments($start, $end)->sum('amount'),
            'customers' => (clone $activeArrivals)->distinct()->count('user_id')
                + (clone $activeArrivals)->whereNull('user_id')->distinct()->count('guest_contact'),
            'bookings' => (clone $activeArrivals)->count(),
            'cancelled' => (clone $arrivals)->where('status', 'cancelled')->count(),
        ];
    }
}
