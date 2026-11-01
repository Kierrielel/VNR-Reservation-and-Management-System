<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    private const TOKEN_PREFIX = 'VNR-CHECKIN:';

    public function __construct(private readonly ReservationLockService $locks) {}

    public function canIssueQr(Booking $booking): bool
    {
        return $booking->status === 'confirmed' && $booking->payment_status === 'paid'
            && $booking->checked_in_at === null && $booking->check_in->gte(today());
    }

    public function token(Booking $booking): string
    {
        return self::TOKEN_PREFIX.Crypt::encryptString((string) $booking->id);
    }

    public function preview(string $token): Booking
    {
        $booking = $this->resolve($token)->load('user', 'cottages', 'eventPackage');
        $this->validateArrival($booking, false);

        return $booking;
    }

    public function confirm(string $token, User $operator): Booking
    {
        abort_unless(in_array($operator->role, ['admin', 'staff'], true), 403);
        $booking = $this->resolve($token);

        return $this->locks->run(['booking:'.$booking->id], function () use ($booking, $operator): Booking {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->validateArrival($booking, true);
            $booking->update(['checked_in_at' => now(), 'checked_in_by' => $operator->id]);

            return $booking;
        });
    }

    private function resolve(string $token): Booking
    {
        if (! str_starts_with($token, self::TOKEN_PREFIX)) {
            throw ValidationException::withMessages(['token' => 'Scan a valid Villa Natividad check-in QR code.']);
        }

        try {
            $id = Crypt::decryptString(substr($token, strlen(self::TOKEN_PREFIX)));
        } catch (DecryptException) {
            throw ValidationException::withMessages(['token' => 'This QR code is invalid or has been altered.']);
        }

        $booking = ctype_digit($id) ? Booking::find($id) : null;
        if (! $booking) {
            throw ValidationException::withMessages(['token' => 'This QR code does not match a reservation.']);
        }

        return $booking;
    }

    private function validateArrival(Booking $booking, bool $requireSettledBalance): void
    {
        if ($booking->checked_in_at !== null) {
            throw ValidationException::withMessages(['token' => 'This reservation has already been checked in.']);
        }

        if ($booking->status !== 'confirmed' || $booking->payment_status !== 'paid') {
            throw ValidationException::withMessages(['token' => 'Only confirmed reservations with a paid reservation fee can check in.']);
        }

        if (! $booking->check_in->isToday()) {
            throw ValidationException::withMessages(['token' => 'Check-in is only available on the reservation arrival date.']);
        }

        if ($requireSettledBalance && (float) $booking->balance > 0) {
            throw ValidationException::withMessages(['token' => 'The remaining balance must be settled before check-in.']);
        }
    }
}
