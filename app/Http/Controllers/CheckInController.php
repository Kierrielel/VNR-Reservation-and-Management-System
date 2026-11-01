<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\CheckInService;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckInController extends Controller
{
    public function index(): View
    {
        return view('CheckIn.index', [
            'walkIns' => Booking::whereNull('user_id')->whereNotNull('guest_name')->latest()->orderByDesc('id')->limit(10)->get(),
            'arrivals' => Booking::with('user', 'checkedInBy')->whereDate('checked_in_at', today())
                ->latest('checked_in_at')->limit(10)->get(),
        ]);
    }

    public function qr(Request $request, Booking $booking, CheckInService $checkIn): Response
    {
        $staffWalkIn = $booking->user_id === null && $booking->guest_name !== null
            && in_array($request->user()->role, ['admin', 'staff'], true);
        abort_unless($booking->user_id === $request->user()->id || $staffWalkIn, 403);
        abort_unless($checkIn->canIssueQr($booking), 409, 'A check-in QR code is not available for this reservation.');
        $result = (new SvgWriter)->write(new QrCode(
            data: $checkIn->token($booking), errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 320, margin: 16,
        ));
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($result->getString(), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => $disposition.'; filename="reservation-'.$booking->id.'-checkin.svg"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, CheckInService $checkIn): JsonResponse
    {
        $validated = $request->validate(['token' => 'required|string|max:2048']);
        $booking = $checkIn->preview($validated['token']);

        return response()->json([
            'reference' => $booking->reference_code ?? '#'.$booking->id,
            'guest' => $booking->guest_display_name,
            'guests' => $booking->guest_count,
            'type' => $booking->reservation_type,
            'accommodation' => $booking->eventPackage?->name ?? $booking->cottages->pluck('name')->join(', '),
            'arrival' => $booking->check_in->toDateString(),
            'departure' => $booking->check_out->toDateString(),
            'balance' => (float) $booking->balance,
            'can_check_in' => (float) $booking->balance <= 0,
            'manage_url' => $request->user()->role === 'admin' ? route('admin.bookings.show', $booking) : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, CheckInService $checkIn): JsonResponse
    {
        $validated = $request->validate(['token' => 'required|string|max:2048']);
        $booking = $checkIn->confirm($validated['token'], $request->user());

        return response()->json([
            'message' => 'Guest checked in successfully.',
            'reference' => $booking->reference_code ?? '#'.$booking->id,
            'checked_in_at' => $booking->checked_in_at->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
