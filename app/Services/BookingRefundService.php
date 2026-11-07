<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BookingRefundService
{
    private const REFUND_WINDOW_HOURS = 24;

    /** @return array{status: string, amount: float, deadline: ?CarbonImmutable} */
    public function assess(Booking $booking, CarbonInterface $cancelledAt): array
    {
        $payments = $booking->payments()->where('status', 'paid')
            ->orderByRaw('COALESCE(paid_at, updated_at, created_at)')->orderBy('id')->get();
        $firstPayment = $payments->first();
        if ($firstPayment === null) {
            return ['status' => 'not_required', 'amount' => 0.0, 'deadline' => null];
        }

        $paidAt = $firstPayment->paid_at ?? $firstPayment->updated_at ?? $firstPayment->created_at;
        $deadline = CarbonImmutable::instance($paidAt)->addHours(self::REFUND_WINDOW_HOURS);
        $eligible = $cancelledAt->getTimestamp() <= $deadline->getTimestamp();

        return [
            'status' => $eligible ? 'pending_approval' : 'not_eligible',
            'amount' => $eligible ? round((float) $payments->sum('amount'), 2) : 0.0,
            'deadline' => $deadline,
        ];
    }

    public function recordEligibility(Booking $booking): void
    {
        if ($booking->status !== 'cancelled' || $booking->cancelled_at === null) {
            return;
        }

        if (in_array($booking->refund_status, ['processing', 'refunded', 'rejected', 'failed', 'needs_review'], true)) {
            $totalPaid = (int) round((float) $booking->payments()->where('status', 'paid')->sum('amount') * 100);
            if ($totalPaid > (int) round((float) $booking->refund_amount * 100)) {
                $booking->update(['refund_status' => 'needs_review']);
            }

            return;
        }

        $refund = $this->assess($booking, $booking->cancelled_at);
        $booking->update([
            'refund_status' => $refund['status'],
            'refund_amount' => $refund['amount'],
            'refund_deadline_at' => $refund['deadline'],
        ]);
    }
}
