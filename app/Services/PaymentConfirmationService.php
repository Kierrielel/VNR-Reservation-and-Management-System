<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Carbon\CarbonInterface;

class PaymentConfirmationService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly BookingRefundService $refunds,
    ) {}

    // The caller verifies the gateway response before confirming a payment.
    public function confirm(Payment $payment, ?string $reference = null, ?CarbonInterface $paidAt = null): void
    {
        $this->locks->run(['booking:'.$payment->booking_id], function () use ($payment, $reference, $paidAt) {
            $booking = Booking::whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'paid' && $booking->payment_status === 'paid') {
                $this->refunds->recordEligibility($booking);

                return;
            }

            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'reference_no' => $reference,
                    'paid_at' => $paidAt ?? now(),
                ]);
            }

            $booking->update([
                'payment_status' => 'paid',
                'payment_method' => $payment->method,
                'status' => $booking->status === 'pending' ? 'confirmed' : $booking->status,
            ]);
            $this->refunds->recordEligibility($booking);
        });
    }
}
