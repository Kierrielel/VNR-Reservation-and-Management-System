<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class BookingCancellationService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly BookingRefundService $refunds,
    ) {}

    public function canCancel(Booking $booking): bool
    {
        return in_array($booking->status, ['pending', 'confirmed'], true)
            && $booking->checked_in_at === null && $booking->check_in->gte(today());
    }

    public function cancel(Booking $booking, User $guest, string $reason): Booking
    {
        abort_unless($booking->user_id === $guest->id, 403);

        return Cache::lock(PayMongoCheckoutService::lockName($booking->id), 120)->block(5,
            fn (): Booking => $this->locks->run(['booking:'.$booking->id], function () use ($booking, $guest, $reason): Booking {
                $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                abort_unless($booking->user_id === $guest->id, 403);
                if (! $this->canCancel($booking)) {
                    throw ValidationException::withMessages([
                        'confirm_cancellation' => 'Only active reservations that have not checked in or passed their arrival date can be cancelled.',
                    ]);
                }

                $booking->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now()->startOfSecond(),
                    'cancelled_by' => $guest->id,
                    'cancellation_reason' => $reason,
                ]);
                $this->refunds->recordEligibility($booking);

                return $booking;
            })
        );
    }
}
