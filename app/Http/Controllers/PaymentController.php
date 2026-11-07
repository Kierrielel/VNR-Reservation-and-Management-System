<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentConfirmationService;
use App\Services\PayMongoCheckoutService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentConfirmationService $confirmation) {}

    /**
     * Create a PayMongo Checkout Session and redirect the user.
     */
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        // Only the owner can pay for their booking
        abort_unless($booking->user_id === Auth::id(), 403);

        try {
            return Cache::lock(PayMongoCheckoutService::lockName($booking->id), 120)
                ->block(5, function () use ($request, $booking): RedirectResponse {
                    $booking->refresh();

                    return $this->createCheckout($request, $booking);
                });
        } catch (LockTimeoutException|ConnectionException) {
            return back()->with('error', 'Payment checkout is temporarily unavailable. Please try again.');
        }
    }

    private function createCheckout(Request $request, Booking $booking): RedirectResponse
    {

        if (! in_array($booking->status, ['pending', 'confirmed'], true)) {
            return back()->with('error', 'This booking no longer accepts payments.');
        }

        if ($booking->expires_at?->lte(now()) && $booking->payment_status !== 'paid') {
            return back()->with('error', 'The payment window has ended. Any existing checkout is being verified before the dates are released.');
        }

        // Don't allow paying twice
        if ($booking->payment_status === 'paid') {
            return redirect()
                ->route('reservations.show', $booking)
                ->with('error', 'This reservation has already been paid.');
        }

        $validated = $request->validate([
            'method' => ['required', Rule::in(['gcash', 'paymaya', 'qrph'])],
        ]);

        $secretKey = config('services.paymongo.secret');

        if (blank($secretKey)) {
            return back()->with('error', 'Payment gateway is not configured. Please contact support.');
        }

        // PayMongo expects amounts in centavos (integer)
        $amountInCentavos = (int) round($booking->reservation_fee * 100);

        // Map our method names to PayMongo payment_method_types
        $methodMap = [
            'gcash' => 'gcash',
            'paymaya' => 'paymaya',
            'qrph' => 'qrph',
        ];

        $response = Http::withBasicAuth($secretKey, '')->connectTimeout(3)->timeout(10)
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => true,
                        'show_description' => true,
                        'show_line_items' => true,
                        'description' => "Reservation fee for {$booking->reference_code}",
                        'line_items' => [
                            [
                                'currency' => 'PHP',
                                'amount' => $amountInCentavos,
                                'name' => "Reservation Fee — {$booking->reference_code}",
                                'quantity' => 1,
                                'description' => $booking->reservation_type === 'event'
                                    ? 'Event reservation fee'
                                    : 'Public pool reservation fee',
                            ],
                        ],
                        'payment_method_types' => [$methodMap[$validated['method']]],
                        'success_url' => route('payment.success', $booking),
                        'cancel_url' => route('reservations.show', $booking),
                        'metadata' => [
                            'booking_id' => $booking->id,
                            'reference_code' => $booking->reference_code,
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::error('PayMongo checkout session failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return back()->with('error', 'Could not create payment session. Please try again.');
        }

        $session = $response->json('data');

        // Store a pending payment record
        Payment::create([
            'booking_id' => $booking->id,
            'method' => $validated['method'],
            'amount' => $booking->reservation_fee,
            'status' => 'pending',
            'checkout_session_id' => $session['id'],
        ]);

        // Redirect user to PayMongo's hosted checkout page
        return redirect()->away($session['attributes']['checkout_url']);
    }

    /**
     * Handle the return from PayMongo after payment.
     */
    public function success(Request $request, Booking $booking, PayMongoCheckoutService $checkout)
    {
        abort_unless($booking->user_id === Auth::id(), 403);

        // Find the most recent payment record for this booking with a checkout session
        $payment = Payment::where('booking_id', $booking->id)
            ->whereNotNull('checkout_session_id')
            ->latest()
            ->first();

        if (! $payment) {
            return redirect()->route('reservations.show', $booking)
                ->with('error', 'Payment record not found.');
        }

        // Already marked paid
        if ($payment->status === 'paid' && $booking->payment_status === 'paid') {
            return redirect()->route('reservations.show', $booking)
                ->with('success', in_array($booking->status, ['cancelled', 'expired'], true)
                    ? 'Payment recorded. This reservation remains closed; please contact the resort.'
                    : 'Payment successful! Your reservation is confirmed.');
        }

        $sessionId = $payment->checkout_session_id;
        $session = $checkout->retrieve($sessionId);
        if ($session === null) {
            return redirect()->route('reservations.show', $booking)
                ->with('error', 'Could not verify payment. Please contact support.');
        }

        $paymongoPaymentId = $checkout->paidPaymentId($session);
        if ($paymongoPaymentId !== null) {
            $this->confirmation->confirm($payment, $paymongoPaymentId, $checkout->paidPaymentTime($session, $paymongoPaymentId));

            return redirect()->route('reservations.show', $booking)
                ->with('success', in_array($booking->fresh()->status, ['cancelled', 'expired'], true)
                    ? 'Payment recorded. This reservation remains closed; please contact the resort.'
                    : 'Payment successful! Your reservation is confirmed.');
        }

        return redirect()->route('reservations.show', $booking)
            ->with('error', 'Payment was not completed. Please try again.');
    }

    /**
     * Handle PayMongo webhooks for async payment confirmation.
     * This is a fallback in case the user closes the browser before returning.
     */
    public function webhook(Request $request, PayMongoCheckoutService $checkout)
    {
        $payload = $request->all();

        $eventType = $payload['data']['attributes']['type'] ?? null;

        if ($eventType !== 'checkout_session.payment.paid') {
            return response()->json(['message' => 'Ignored'], 200);
        }

        $sessionData = $payload['data']['attributes']['data'] ?? [];
        $sessionId = $sessionData['id'] ?? null;

        if (! $sessionId) {
            return response()->json(['message' => 'No session ID'], 400);
        }

        $payment = Payment::where('checkout_session_id', $sessionId)->first();

        if (! $payment || $payment->status === 'paid') {
            return response()->json(['message' => 'Already processed or not found'], 200);
        }

        $paymentsArray = $sessionData['attributes']['payments'] ?? [];
        $paymongoPaymentId = $paymentsArray[0]['id'] ?? null;

        $this->confirmation->confirm($payment, $paymongoPaymentId,
            is_string($paymongoPaymentId) ? $checkout->paidPaymentTime($sessionData, $paymongoPaymentId) : null);

        Log::info('Webhook: Payment confirmed', [
            'booking_id' => $payment->booking_id,
            'session_id' => $sessionId,
        ]);

        return response()->json(['message' => 'OK'], 200);
    }
}
