<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentRefund;

class RefundProcessingService
{
    public function __construct(
        private readonly PayMongoRefundService $gateway,
        private readonly PayMongoCheckoutService $checkout,
        private readonly ReservationLockService $locks,
    ) {}

    public function process(Booking $booking): void
    {
        $queued = $booking->refunds()->where('status', 'queued')->with('payment')->get();
        foreach ($queued as $refund) {
            $verification = $this->verifyPayment($refund, $booking);
            if ($verification === null) {
                $refund->update(['error' => 'Payment verification is temporarily unavailable. It will be checked again before submitting the refund.']);
                $this->syncBooking($booking);

                return;
            }
            if (! $verification) {
                $booking->refunds()->where('status', 'queued')->update([
                    'status' => 'failed', 'error' => 'The original charge or 24-hour refund eligibility could not be verified. No new refund was submitted for this payment.',
                ]);
                $this->syncBooking($booking);

                return;
            }
        }

        foreach ($booking->refunds()->orderBy('id')->get() as $refund) {
            if ($refund->status === 'queued') {
                $claimed = PaymentRefund::whereKey($refund->id)->where('status', 'queued')->update([
                    'status' => 'submitting', 'attempted_at' => now(),
                ]);
                if (! $claimed) {
                    continue;
                }
                $result = $this->gateway->create($refund->gateway_payment_id, $this->cents($refund->amount), $refund->idempotency_key, $refund->notes);
                if (($result['status'] ?? null) === 'rejected') {
                    $refund->update(['status' => 'failed', 'error' => $result['error']]);
                } else {
                    $this->recordResponse($refund, $result);
                }
            } elseif (in_array($refund->status, ['pending', 'processing', 'submitting', 'unknown'], true)) {
                $result = $refund->gateway_refund_id
                    ? $this->gateway->fetch($refund->gateway_refund_id)
                    : $this->findUncertainRefund($refund);
                $this->recordResponse($refund, $result);
            }
        }

        $this->syncBooking($booking);
    }

    private function verifyPayment(PaymentRefund $refund, Booking $booking): ?bool
    {
        $payment = $refund->payment;
        $session = $this->checkout->retrieve($payment->checkout_session_id);
        if ($session === null) {
            return null;
        }
        if (($session['id'] ?? null) !== $payment->checkout_session_id
            || (string) ($session['attributes']['metadata']['booking_id'] ?? '') !== (string) $refund->booking_id
            || $this->checkout->paidPaymentId($session) !== $refund->gateway_payment_id) {
            return false;
        }
        $charge = $this->gateway->payment($refund->gateway_payment_id);
        if ($charge === null) {
            return null;
        }
        $attributes = $charge['attributes'] ?? [];
        $live = str_starts_with((string) config('services.paymongo.secret'), 'sk_live_');
        $paidAt = $attributes['paid_at'] ?? null;

        return ($charge['id'] ?? null) === $refund->gateway_payment_id
            && ($attributes['status'] ?? null) === 'paid'
            && ($attributes['currency'] ?? null) === 'PHP'
            && ($attributes['amount'] ?? null) === $this->cents($refund->amount)
            && ($attributes['livemode'] ?? null) === $live
            && is_int($paidAt) && $paidAt > 0 && $paidAt <= now()->getTimestamp()
            && $booking->cancelled_at->getTimestamp() <= $paidAt + 24 * 60 * 60;
    }

    private function findUncertainRefund(PaymentRefund $refund): ?array
    {
        $after = null;
        for ($page = 0; $page < 10; $page++) {
            $list = $this->gateway->listForPayment($refund->gateway_payment_id, $after);
            if ($list === null) {
                return null;
            }
            foreach ($list['data'] as $item) {
                if (($item['notes'] ?? null) === $refund->notes
                    || ($item['metadata']['refund_request_id'] ?? null) === $refund->idempotency_key) {
                    return $item;
                }
            }
            if (! $list['has_more'] || $list['data'] === []) {
                return null;
            }
            $after = $list['data'][array_key_last($list['data'])]['id'];
        }

        return null;
    }

    private function recordResponse(PaymentRefund $refund, ?array $result): void
    {
        if ($result === null || ($result['payment_id'] ?? null) !== $refund->gateway_payment_id
            || ($result['amount'] ?? null) !== $this->cents($refund->amount)
            || ! in_array($result['status'] ?? null, ['pending', 'processing', 'succeeded', 'failed'], true)
            || ($refund->gateway_refund_id && ($result['id'] ?? null) !== $refund->gateway_refund_id)
            || (isset($result['currency']) && $result['currency'] !== 'PHP')
            || (isset($result['livemode']) && $result['livemode'] !== str_starts_with((string) config('services.paymongo.secret'), 'sk_live_'))) {
            $refund->update(['status' => 'unknown', 'error' => 'The refund result is uncertain. Refresh its status; do not submit another refund.']);

            return;
        }

        $refund->update([
            'gateway_refund_id' => $result['id'], 'status' => $result['status'],
            'error' => $result['status'] === 'failed' ? 'PayMongo reported that this refund failed.' : null,
            'refunded_at' => $result['status'] === 'succeeded' ? ($refund->refunded_at ?? now()) : null,
        ]);
    }

    private function syncBooking(Booking $booking): void
    {
        $this->locks->run(['booking:'.$booking->id], function () use ($booking): void {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $refunds = $booking->refunds()->get();
            if ($refunds->isEmpty()) {
                return;
            }
            $extraPayment = $this->cents($booking->payments()->where('status', 'paid')->sum('amount')) > $this->cents($refunds->sum('amount'));
            $status = match (true) {
                $extraPayment, $refunds->contains('status', 'unknown'), $refunds->contains('status', 'submitting') => 'needs_review',
                $refunds->every(fn (PaymentRefund $refund): bool => $refund->status === 'succeeded') => 'refunded',
                $refunds->contains(fn (PaymentRefund $refund): bool => in_array($refund->status, ['queued', 'pending', 'processing'], true)) => 'processing',
                default => 'failed',
            };
            $booking->update(['refund_status' => $status]);
        });
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
