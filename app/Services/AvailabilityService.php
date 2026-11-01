<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Cottage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityService
{
    private const ACTIVE_STATUSES = ['pending', 'confirmed'];

    public function activeBookings(): Builder
    {
        return Booking::whereIn('status', self::ACTIVE_STATUSES);
    }

    public function availableCottages(string $checkIn, string $checkOut, ?string $stayType = null): Builder
    {
        $query = Cottage::where('status', 'available')
            ->whereDoesntHave('bookings', function (Builder $query) use ($checkIn, $checkOut) {
                $this->overlappingBookings($query, $checkIn, $checkOut);
            });

        if ($stayType === 'overnight') {
            $query->whereNotNull('price_overnight');
        }

        return $query;
    }

    public function occupiedCottages(string $checkIn, string $checkOut): Builder
    {
        return Cottage::whereHas('bookings', function (Builder $query) use ($checkIn, $checkOut): void {
            $this->overlappingBookings($query, $checkIn, $checkOut);
        });
    }

    private function overlappingBookings(Builder $query, string $checkIn, string $checkOut): void
    {
        // Inclusive dates also block day tours whose check-in and check-out match.
        $query->whereIn('bookings.status', self::ACTIVE_STATUSES)
            ->whereDate('bookings.check_in', '<=', $checkOut)
            ->whereDate('bookings.check_out', '>=', $checkIn);
    }

    public function eventDateTaken(string $date): bool
    {
        return $this->activeBookings()
            ->where('reservation_type', 'event')
            ->whereDate('check_in', $date)
            ->exists();
    }

    /** @return array{0: string, 1: string} */
    public function resolveDates(string $checkIn, string $stayType): array
    {
        $in = Carbon::parse($checkIn);
        $out = $stayType === 'overnight' ? $in->copy()->addDay() : $in->copy();

        return [$in->toDateString(), $out->toDateString()];
    }
}
