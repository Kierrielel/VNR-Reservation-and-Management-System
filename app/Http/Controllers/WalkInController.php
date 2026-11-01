<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\CheckInService;
use App\Services\ReservationLockService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WalkInController extends Controller
{
    public function show(Booking $booking, CheckInService $checkIn): View
    {
        $this->ensureWalkIn($booking);

        return view('CheckIn.walk-in', [
            'booking' => $booking->load('cottages', 'eventPackage', 'payments', 'bookedBy', 'checkedInBy'),
            'canShowQr' => $checkIn->canIssueQr($booking),
        ]);
    }

    public function pay(Request $request, Booking $booking, ReservationLockService $locks): RedirectResponse
    {
        $this->ensureWalkIn($booking);
        $request->validate(['payment_received' => 'required|accepted']);

        $locks->run(['booking:'.$booking->id], function () use ($booking): void {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->status !== 'pending' || $booking->payment_status !== 'unpaid'
                || $booking->expires_at?->lte(now())) {
                throw ValidationException::withMessages(['payment_received' => 'This booking is already paid, closed, or past its payment deadline.']);
            }
            $booking->payments()->create([
                'method' => 'cash', 'amount' => $booking->total_price, 'status' => 'paid',
                'paid_at' => now(), 'reference_no' => 'WALKIN-'.$booking->reference_code,
            ]);
            $booking->update([
                'status' => 'confirmed', 'payment_status' => 'paid', 'payment_method' => 'cash',
                'balance' => 0, 'expires_at' => null,
            ]);
        });

        return redirect()->route('walk-ins.show', $booking)->with('success', 'Full cash payment recorded. The booking is confirmed.');
    }

    public function checkIn(Request $request, Booking $booking, CheckInService $checkIn): RedirectResponse
    {
        $this->ensureWalkIn($booking);
        $checkIn->confirm($checkIn->token($booking), $request->user());

        return redirect()->route('walk-ins.show', $booking)->with('success', 'Walk-in guest checked in successfully.');
    }

    private function ensureWalkIn(Booking $booking): void
    {
        abort_unless($booking->user_id === null && $booking->guest_name !== null, 404);
    }
}
