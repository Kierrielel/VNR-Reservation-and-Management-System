<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;

class PaymentConfirmationService
{
    public function __construct(private readonly ReservationLockService $locks) {}

    // The caller verifies the gateway response before confirming a payment.
    public function confirm(Payment $payment, ?string $reference = null): void
    {
        $this->locks->run(['booking:'.$payment->booking_id], function () use ($payment, $reference) {
            $booking = Booking::whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'paid' && $booking->payment_status === 'paid') {
                return;
            }

            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'reference_no' => $reference,
                    'paid_at' => now(),
                ]);
            }

            $booking->update([
                'payment_status' => 'paid',
                'payment_method' => $payment->method,
                'status' => $booking->status === 'pending' ? 'confirmed' : $booking->status,
            ]);
        });
    }
}
