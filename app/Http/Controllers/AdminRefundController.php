<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRefundRequest;
use App\Models\Booking;
use App\Services\RefundApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminRefundController extends Controller
{
    public function update(ReviewRefundRequest $request, Booking $booking, RefundApprovalService $refunds): RedirectResponse
    {
        $validated = $request->validated();
        $booking = $validated['decision'] === 'approve'
            ? $refunds->approve($booking, $request->user())
            : $refunds->reject($booking, $request->user(), $validated['rejection_reason']);

        $message = match ($booking->refund_status) {
            'rejected' => 'Refund disapproved. No refund has been submitted to PayMongo.',
            'refunded' => 'PayMongo reports that the refund is complete.',
            'processing' => 'Refund approved. Check the refund status while PayMongo processes it.',
            'failed' => 'The refund could not be completed. Review the refund status before taking further action.',
            'needs_review' => 'The refund needs review. Check its PayMongo status before making another refund request.',
            default => 'Refund approval recorded. Check the refund status for the latest result.',
        };
        $flash = in_array($booking->refund_status, ['failed', 'needs_review'], true) ? 'error' : 'success';

        return redirect()->route('admin.bookings.show', $booking)->with($flash, $message);
    }

    public function refresh(Request $request, Booking $booking, RefundApprovalService $refunds): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $refunds->reconcile($booking);

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Refund status checked. Review the latest result below.');
    }
}
