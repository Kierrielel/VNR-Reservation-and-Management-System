<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundApprovalService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly BookingRefundService $eligibility,
        private readonly RefundProcessingService $processor,
    ) {}

    public function approve(Booking $booking, User $admin): Booking
    {
        abort_unless($admin->role === 'admin', 403);

        return $this->withLock($booking, function () use ($booking, $admin): Booking {
            $this->locks->run(['booking:'.$booking->id], function () use ($booking, $admin): void {
                $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                $this->ensureReviewable($booking);
                $assessment = $this->eligibility->assess($booking, $booking->cancelled_at);
                if ($assessment['status'] !== 'pending_approval' || $assessment['amount'] <= 0) {
                    throw ValidationException::withMessages(['refund' => 'This cancellation is not eligible for a refund.']);
                }
                $payments = $booking->payments()->where('status', 'paid')->get();
                if ($payments->pluck('reference_no')->unique()->count() !== $payments->count()) {
                    throw ValidationException::withMessages(['refund' => 'Duplicate payment references need review before a refund can be approved.']);
                }
                foreach ($payments as $payment) {
                    if (blank($payment->checkout_session_id) || ! preg_match('/^pay_[A-Za-z0-9]+$/', (string) $payment->reference_no)
                        || (float) $payment->amount <= 0) {
                        throw ValidationException::withMessages(['refund' => 'Every payment must be a verified PayMongo checkout payment. Cash or manually recorded payments cannot be refunded through PayMongo.']);
                    }
                }
                foreach ($payments as $payment) {
                    $key = (string) Str::uuid();
                    PaymentRefund::create([
                        'booking_id' => $booking->id, 'payment_id' => $payment->id,
                        'gateway_payment_id' => $payment->reference_no, 'amount' => $payment->amount,
                        'status' => 'queued', 'idempotency_key' => $key,
                        'notes' => 'Reservation '.$booking->id.' cancellation; refund request '.$key,
                    ]);
                }
                $booking->update([
                    'refund_status' => 'processing', 'refund_amount' => $assessment['amount'],
                    'refund_decided_at' => now(), 'refund_decided_by' => $admin->id,
                    'refund_rejection_reason' => null,
                ]);
            });

            $this->processor->process($booking->fresh());

            return $booking->fresh();
        });
    }

    public function reject(Booking $booking, User $admin, string $reason): Booking
    {
        abort_unless($admin->role === 'admin', 403);

        return $this->withLock($booking, fn (): Booking => $this->locks->run(['booking:'.$booking->id], function () use ($booking, $admin, $reason): Booking {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->ensureReviewable($booking);
            $booking->update([
                'refund_status' => 'rejected', 'refund_decided_at' => now(),
                'refund_decided_by' => $admin->id, 'refund_rejection_reason' => $reason,
            ]);

            return $booking;
        }));
    }

    public function reconcile(Booking $booking): Booking
    {
        return $this->withLock($booking, function () use ($booking): Booking {
            $booking->refresh();
            if (in_array($booking->refund_status, ['processing', 'needs_review'], true)) {
                $this->processor->process($booking);
            }

            return $booking->fresh();
        });
    }

    private function ensureReviewable(Booking $booking): void
    {
        if ($booking->status !== 'cancelled' || $booking->cancelled_at === null
            || $booking->checked_in_at !== null || $booking->refund_status !== 'pending_approval'
            || $booking->refunds()->exists()) {
            throw ValidationException::withMessages(['refund' => 'This refund has already been reviewed or is not awaiting approval.']);
        }
    }

    private function withLock(Booking $booking, \Closure $callback): Booking
    {
        try {
            return Cache::lock('booking-refund:'.$booking->id, 180)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['refund' => 'A refund operation is already in progress. Please refresh its status shortly.']);
        }
    }
}
