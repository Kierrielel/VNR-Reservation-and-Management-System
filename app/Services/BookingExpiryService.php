<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingExpiryService
{
    public function __construct(
        private readonly PayMongoCheckoutService $checkout,
        private readonly PaymentConfirmationService $confirmation,
        private readonly ReservationLockService $locks,
    ) {}

    /** @return array{expired: int, confirmed: int, deferred: int} */
    public function expireDue(): array
    {
        $counts = ['expired' => 0, 'confirmed' => 0, 'deferred' => 0];
        $bookings = Booking::where('status', 'pending')->where('payment_status', '!=', 'paid')
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('expires_at')->limit(100)->get();

        foreach ($bookings as $booking) {
            try {
                $result = Cache::lock(PayMongoCheckoutService::lockName($booking->id), 120)
                    ->block(5, fn (): string => $this->expireBooking($booking));
                $counts[$result]++;
            } catch (Throwable $exception) {
                Log::warning('Reservation expiry deferred', ['booking_id' => $booking->id, 'exception' => $exception::class]);
                $counts['deferred']++;
            }
        }

        return $counts;
    }

    private function expireBooking(Booking $booking): string
    {
        $booking->refresh();
        if ($booking->status !== 'pending' || $booking->payment_status === 'paid') {
            return 'deferred';
        }

        foreach ($booking->payments()->whereNotNull('checkout_session_id')->where('status', '!=', 'expired')->get() as $payment) {
            $session = $this->checkout->retrieve($payment->checkout_session_id);
            if ($session === null) {
                return 'deferred';
            }

            $paidId = $this->checkout->paidPaymentId($session);
            if ($paidId !== null) {
                $this->confirmation->confirm($payment, $paidId);

                return 'confirmed';
            }

            if (($session['attributes']['status'] ?? null) !== 'expired') {
                $session = $this->checkout->expire($payment->checkout_session_id)
                    ?? $this->checkout->retrieve($payment->checkout_session_id);
                $paidId = $session === null ? null : $this->checkout->paidPaymentId($session);
                if ($paidId !== null) {
                    $this->confirmation->confirm($payment, $paidId);

                    return 'confirmed';
                }
                if (($session['attributes']['status'] ?? null) !== 'expired') {
                    return 'deferred';
                }
            }
        }

        return $this->locks->run(['booking:'.$booking->id], function () use ($booking): string {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->status !== 'pending' || $booking->payment_status === 'paid'
                || $booking->payments()->where('status', 'paid')->exists()) {
                return 'deferred';
            }

            $booking->payments()->where('status', 'pending')->update(['status' => 'expired']);
            $booking->update(['status' => 'expired']);

            return 'expired';
        });
    }
}
