<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\EventPackage;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    // Only these statuses block a cottage or a date. Cancelled bookings free it up.
    private const ACTIVE_STATUSES = ['pending', 'confirmed'];

    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'stay_type' => 'required|in:day_tour,overnight',
        ]);

        [$checkIn, $checkOut] = $this->resolveDates($validated['check_in'], $validated['stay_type']);

        return response()->json(
            $this->availableCottages($checkIn, $checkOut, $validated['stay_type'])->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservation_type' => 'required|in:public,event',
            'stay_type' => 'required_if:reservation_type,public|nullable|in:day_tour,overnight',
            'cottage_ids' => 'required_if:reservation_type,public|nullable|array|min:1|max:2',
            'cottage_ids.*' => 'integer|distinct|exists:cottages,id',
            'event_package_id' => 'required_if:reservation_type,event|nullable|exists:event_packages,id',
            'check_in' => 'required|date|after_or_equal:today',
            'guest_count' => 'required|integer|min:1',
            'additional_message' => 'nullable|string|max:1000',
        ]);

        $isEvent = $validated['reservation_type'] === 'event';
        $stayType = $isEvent ? null : $validated['stay_type'];

        // Check-out never comes from the form. Day tours and events end the same day,
        // overnight stays end the next day.
        [$checkIn, $checkOut] = $this->resolveDates($validated['check_in'], $stayType ?? 'day_tour');

        $booking = DB::transaction(function () use ($validated, $isEvent, $stayType, $checkIn, $checkOut) {
            $package = null;

            if ($isEvent) {
                $package = EventPackage::findOrFail($validated['event_package_id']);

                if ($package->status !== 'available') {
                    throw ValidationException::withMessages([
                        'event_package_id' => 'This package is not available.',
                    ]);
                }

                if ($this->eventDateTaken($checkIn)) {
                    throw ValidationException::withMessages([
                        'check_in' => 'That date is already booked for an event.',
                    ]);
                }

                $total = (float) $package->price;
            } else {
                // Re-check availability here. The earlier check from the form can be stale.
                $available = $this->availableCottages($checkIn, $checkOut, $stayType)->pluck('id')->all();

                if (array_diff($validated['cottage_ids'], $available)) {
                    throw ValidationException::withMessages([
                        'cottage_ids' => 'One or more selected cottages are not available for those dates.',
                    ]);
                }

                $total = $this->calculateTotal(
                    $validated['cottage_ids'],
                    $stayType,
                    (int) $validated['guest_count']
                );
            }

            $feePercent = (float) (Setting::where('key', 'reservation_fee_percent')->value('value') ?? 20);
            $reservationFee = round($total * $feePercent / 100, 2);

            $booking = Booking::create([
                'user_id' => Auth::id(),
                'event_package_id' => $package?->id,
                'reservation_type' => $validated['reservation_type'],
                'stay_type' => $stayType,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_count' => (int) $validated['guest_count'],
                'total_price' => $total,
                'reservation_fee' => $reservationFee,
                'balance' => $total - $reservationFee,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'reference_code' => $this->generateReferenceCode(),
                'additional_message' => $validated['additional_message'] ?? null,
            ]);

            if (! $isEvent) {
                $booking->cottages()->attach($validated['cottage_ids']);
            }

            return $booking;
        });

        return redirect()
            ->route('reservations.show', $booking)
            ->with('success', 'Reservation created.');
    }

    public function show(Booking $booking)
    {
        // A guest can only open their own booking.
        abort_unless($booking->user_id === Auth::id(), 403);

        return view('reservations.show', [
            'booking' => $booking->load('cottages', 'eventPackage'),
        ]);
    }

    private function calculateTotal(array $cottageIds, string $stayType, int $guestCount)
    {
        $cottages = Cottage::whereIn('id', $cottageIds)->get();

        if ($cottages->count() !== count(array_unique($cottageIds))) {
            throw new \InvalidArgumentException('One or more selected cottages do not exist.');
        }

        if ($stayType === 'overnight' && $cottages->contains(fn ($cottage) => $cottage->price_overnight === null)) {
            throw new \InvalidArgumentException('A selected cottage is not available for overnight stays.');
        }

        $cottagePrice = $cottages->sum(function ($cottage) use ($stayType) {
            return $stayType === 'overnight' ? $cottage->price_overnight : $cottage->price_day;
        });

        $guestRates = Setting::whereIn('key', ['guest_rate_day', 'guest_rate_overnight'])->pluck('value', 'key');

        $rateDay = (float) ($guestRates['guest_rate_day'] ?? 150);
        $rateOvernight = (float) ($guestRates['guest_rate_overnight'] ?? 200);

        $guestPrice = $guestCount * ($stayType === 'overnight' ? $rateOvernight : $rateDay);

        return (float) $cottagePrice + $guestPrice;
    }

    private function availableCottages(string $checkIn, string $checkOut, string $stayType)
    {
        $query = Cottage::where('status', 'available')
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                // Inclusive comparison so a day tour (check_in = check_out) still blocks its own date.
                // whereDate is used because SQLite stores dates as text and plain comparison can misfire.
                $q->whereIn('bookings.status', self::ACTIVE_STATUSES)
                    ->whereDate('bookings.check_in', '<=', $checkOut)
                    ->whereDate('bookings.check_out', '>=', $checkIn);
            });

        // Nipa huts and umbrella cottages have no overnight price, so they cannot be booked overnight.
        if ($stayType === 'overnight') {
            $query->whereNotNull('price_overnight');
        }

        return $query;
    }

    private function eventDateTaken(string $date): bool
    {
        return Booking::where('reservation_type', 'event')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereDate('check_in', $date)
            ->exists();
    }

    private function resolveDates(string $checkIn, string $stayType): array
    {
        $in = Carbon::parse($checkIn);
        $out = $stayType === 'overnight' ? $in->copy()->addDay() : $in->copy();

        return [$in->toDateString(), $out->toDateString()];
    }

    private function generateReferenceCode(): string
    {
        do {
            $code = 'VNR-' . strtoupper(Str::random(8));
        } while (Booking::where('reference_code', $code)->exists());

        return $code;
    }
}