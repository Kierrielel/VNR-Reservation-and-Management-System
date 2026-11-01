<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Validation\ValidationException;

class AdminBookingService
{
    public function __construct(private readonly ReservationLockService $locks) {}

    public function updateStatus(Booking $booking, string $status): void
    {
        $this->locks->run(['booking:'.$booking->id], function () use ($booking, $status): void {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($status === 'cancelled' && $booking->checked_in_at !== null) {
                throw ValidationException::withMessages(['status' => 'A checked-in reservation cannot be cancelled. Complete it after departure.']);
            }

            $allowed = match ($booking->status) {
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['completed', 'cancelled'],
                default => [],
            };

            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'This booking cannot move to the selected status.']);
            }

            if ($status === 'confirmed' && $booking->payment_status !== 'paid') {
                throw ValidationException::withMessages(['status' => 'The reservation fee must be paid before confirmation.']);
            }

            if ($status === 'completed' && ($booking->payment_status !== 'paid' || (float) $booking->balance > 0)) {
                throw ValidationException::withMessages(['status' => 'Collect the remaining balance before completing this booking.']);
            }

            if ($status === 'completed' && $booking->check_out->isAfter(today())) {
                throw ValidationException::withMessages(['status' => 'This booking cannot be completed before its check-out date.']);
            }

            $booking->update(['status' => $status]);
        });
    }

    public function settleBalance(Booking $booking, string $method, ?string $reference): void
    {
        $this->locks->run(['booking:'.$booking->id], function () use ($booking, $method, $reference): void {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== 'confirmed' || $booking->payment_status !== 'paid') {
                throw ValidationException::withMessages(['payment' => 'Only confirmed bookings with a paid reservation fee can receive a balance payment.']);
            }

            if ((float) $booking->balance <= 0) {
                throw ValidationException::withMessages(['payment' => 'This booking has no remaining balance.']);
            }

            $booking->payments()->create([
                'method' => $method,
                'amount' => $booking->balance,
                'status' => 'paid',
                'reference_no' => $reference,
                'paid_at' => now(),
            ]);
            $booking->update(['balance' => 0]);
        });
    }
}
