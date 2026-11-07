<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelReservationRequest;
use App\Models\Booking;
use App\Services\BookingCancellationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;

class ReservationCancellationController extends Controller
{
    public function store(CancelReservationRequest $request, Booking $booking, BookingCancellationService $cancellation): RedirectResponse
    {
        try {
            $booking = $cancellation->cancel($booking, $request->user(), $request->validated('cancellation_reason'));
        } catch (LockTimeoutException) {
            return back()->with('error', 'Your payment is being processed. Please try cancelling again shortly.');
        }

        $message = match ($booking->refund_status) {
            'pending_approval' => 'Reservation cancelled. Your refund request is awaiting admin approval. Once approved, the refund will be submitted to PayMongo.',
            'not_eligible' => 'Reservation cancelled. More than 24 hours have passed since the reservation fee was paid, so the payment is non-refundable.',
            default => 'Reservation cancelled. No completed payment is recorded to refund.',
        };

        return redirect()->route('reservations.show', $booking)->with('success', $message);
    }
}
