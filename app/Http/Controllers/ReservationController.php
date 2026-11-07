<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Cottage;
use App\Models\EventPackage;
use App\Models\Setting;
use App\Services\AvailabilityService;
use App\Services\BookingCancellationService;
use App\Services\BookingRefundService;
use App\Services\CheckInService;
use App\Services\ReservationLockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ReservationLockService $reservationLocks,
    ) {}

    public function create(Request $request)
    {
        return view('LoginReg.booking', [
            'packages' => EventPackage::where('status', 'available')->orderBy('price')->get(),
            'reservationFeePercent' => (float) Setting::getValue('reservation_fee_percent', 20),
            'earliestBookingDate' => $this->earliestBookingDate($request)->toDateString(),
            'walkIn' => $request->routeIs('walk-ins.create', 'admin.book'),
        ]);
    }

    /**
     * Return an array of date strings (Y-m-d) that should be disabled in the calendar.
     *
     * - Events: any date that already has an active event booking.
     * - Public pool: any date where ALL eligible cottages are already booked.
     */
    public function disabledDates(Request $request)
    {
        $earliestDate = $this->earliestBookingDate($request);
        $validated = $request->validate([
            'reservation_type' => 'required|in:public,event',
            'stay_type' => 'nullable|required_if:reservation_type,public|in:day_tour,overnight',
        ]);

        if ($validated['reservation_type'] === 'event') {
            return response()->json(
                $this->availability->activeBookings()->where('reservation_type', 'event')
                    ->whereDate('check_in', '>=', $earliestDate)
                    ->pluck('check_in')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->unique()
                    ->values()
            );
        }

        // Public pool — find dates where zero cottages remain available.
        // We only check dates touched by an existing booking (no bookings = all cottages free).
        $stayType = $validated['stay_type'];

        $candidateDates = $this->availability->activeBookings()
            ->whereDate('check_out', '>=', $earliestDate)
            ->get(['check_in', 'check_out'])
            ->flatMap(function ($b) {
                $dates = [];
                $d = Carbon::parse($b->check_in);
                $end = Carbon::parse($b->check_out);
                while ($d->lte($end)) {
                    $dates[] = $d->toDateString();
                    $d->addDay();
                }

                return $dates;
            })
            ->unique()
            ->filter(fn ($d) => Carbon::parse($d)->gte($earliestDate))
            ->values();

        $disabled = [];

        foreach ($candidateDates as $date) {
            [$checkIn, $checkOut] = $this->availability->resolveDates($date, $stayType);

            if ($this->availability->availableCottages($checkIn, $checkOut, $stayType)->count() === 0) {
                $disabled[] = $date;
            }
        }

        return response()->json($disabled);
    }

    public function index()
    {
        $bookings = Booking::where('user_id', Auth::id())
            ->with('cottages', 'eventPackage')
            ->latest('check_in')
            ->get();

        return view('reservations.index', compact('bookings'));
    }

    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'check_in' => 'required|date|after_or_equal:'.$this->earliestBookingDate($request)->toDateString(),
            'stay_type' => 'required|in:day_tour,overnight',
        ]);

        [$checkIn, $checkOut] = $this->availability->resolveDates($validated['check_in'], $validated['stay_type']);

        return response()->json(
            $this->availability->availableCottages($checkIn, $checkOut, $validated['stay_type'])->get()
        );
    }

    public function store(Request $request)
    {
        $walkIn = $request->routeIs('walk-ins.store');
        $validated = $request->validate([
            'reservation_type' => 'required|in:public,event',
            'stay_type' => 'required_if:reservation_type,public|nullable|in:day_tour,overnight',
            'cottage_ids' => 'required_if:reservation_type,public|nullable|array|min:1|max:2',
            'cottage_ids.*' => 'integer|distinct|exists:cottages,id',
            'event_package_id' => 'required_if:reservation_type,event|nullable|exists:event_packages,id',
            'check_in' => 'required|date|after_or_equal:'.$this->earliestBookingDate($request)->toDateString(),
            'guest_count' => 'required|integer|min:1',
            'additional_message' => 'nullable|string|max:1000',
            'guest_name' => $walkIn ? 'required|string|max:255' : 'exclude',
            'guest_contact' => $walkIn ? 'required|string|max:50' : 'exclude',
            'guest_email' => $walkIn ? 'nullable|email|max:255' : 'exclude',
        ]);

        $isEvent = $validated['reservation_type'] === 'event';
        $stayType = $isEvent ? null : $validated['stay_type'];

        // Check-out never comes from the form. Day tours and events end the same day,
        // overnight stays end the next day.
        [$checkIn, $checkOut] = $this->availability->resolveDates($validated['check_in'], $stayType ?? 'day_tour');

        $resources = $isEvent
            ? ['event:venue']
            : array_map(fn ($id): string => 'cottage:'.$id, $validated['cottage_ids']);

        $booking = $this->reservationLocks->run($resources, function () use ($validated, $isEvent, $stayType, $checkIn, $checkOut, $walkIn) {
            $package = null;

            if ($isEvent) {
                $package = EventPackage::findOrFail($validated['event_package_id']);

                if ($package->status !== 'available') {
                    throw ValidationException::withMessages([
                        'event_package_id' => 'This package is not available.',
                    ]);
                }

                if ($this->availability->eventDateTaken($checkIn)) {
                    throw ValidationException::withMessages([
                        'check_in' => 'That date is already booked for an event.',
                    ]);
                }

                $total = (float) $package->price;
            } else {
                // Re-check availability here. The earlier check from the form can be stale.
                $available = $this->availability->availableCottages($checkIn, $checkOut, $stayType)->pluck('id')->all();

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
                'user_id' => $walkIn ? null : Auth::id(),
                'guest_name' => $validated['guest_name'] ?? null,
                'guest_contact' => $validated['guest_contact'] ?? null,
                'guest_email' => $validated['guest_email'] ?? null,
                'booked_by' => $walkIn ? Auth::id() : null,
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
                'expires_at' => now()->addMinutes(config('reservations.hold_minutes')),
            ]);

            if (! $isEvent) {
                $booking->cottages()->attach($validated['cottage_ids']);
            }

            return $booking;
        });

        return redirect()
            ->route($walkIn ? 'walk-ins.show' : 'reservations.show', $booking)
            ->with('success', 'Reservation created.');
    }

    public function show(Booking $booking, CheckInService $checkIn, BookingCancellationService $cancellation, BookingRefundService $refunds)
    {
        // A guest can only open their own booking.
        abort_unless($booking->user_id === Auth::id(), 403);

        return view('reservations.show', [
            'booking' => $booking->load('cottages', 'eventPackage'),
            'canShowCheckInQr' => $checkIn->canIssueQr($booking),
            'canCancelReservation' => $cancellation->canCancel($booking),
            'refundPreview' => $cancellation->canCancel($booking) ? $refunds->assess($booking, now()) : null,
        ]);
    }

    private function earliestBookingDate(Request $request): Carbon
    {
        return in_array($request->user()?->role, ['admin', 'staff'], true)
            ? Carbon::today()
            : Carbon::tomorrow();
    }

    private function calculateTotal(array $cottageIds, string $stayType, int $guestCount): float
    {
        $cottages = Cottage::whereIn('id', $cottageIds)->get();

        if ($cottages->count() !== count(array_unique($cottageIds))) {
            throw new \InvalidArgumentException('One or more selected cottages do not exist.');
        }

        $capacity = (int) $cottages->sum('capacity');
        if ($guestCount > $capacity) {
            throw ValidationException::withMessages([
                'guest_count' => "The selected accommodations can hold a maximum of {$capacity} guests. Select another accommodation (up to 2 total) or reduce the number of guests.",
            ]);
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

    private function generateReferenceCode(): string
    {
        do {
            $code = 'VNR-'.strtoupper(Str::random(8));
        } while (Booking::where('reference_code', $code)->exists());

        return $code;
    }
}
