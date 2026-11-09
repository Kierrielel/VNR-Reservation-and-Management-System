
@extends('LoginReg.layout')

@section('title', 'Reservation ' . $booking->reference_code . ' | Villa Natividad')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reservation-details.css') }}">
@endpush

@section('content')
    <section class="reservation-details">

        {{-- PAGE HEADER --}}
        <div class="details-page-header">
            <div> 
                <h1>Reservation Details</h1>
                <p>Review your booking information and payment status.</p>
            </div>

            <a href="{{ route('reservations.index') }}" class="details-back-button">
                Back to My Reservations
            </a>
        </div>

        {{-- ALERT MESSAGES --}}
        @if (session('success'))
            <div class="details-alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="details-alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="details-alert alert-error" role="alert">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- TWO-COLUMN LAYOUT --}}
        <div class="reservation-details-grid">

            {{-- LEFT COLUMN: RESERVATION INFORMATION --}}
            <div class="details-column">

                {{-- RESERVATION OVERVIEW --}}
                <div class="reservation-overview">
                    <div class="overview-reference">
                        <div>
                            <span class="details-label">REFERENCE NUMBER</span>
                            <h2>{{ $booking->reference_code }}</h2>
                        </div>

                        <span class="reservation-status status-{{ strtolower($booking->status) }}">
                            {{ ucfirst($booking->status) }}
                        </span>
                    </div>

                    <div class="overview-grid">
                        <div class="overview-item">
                            <span class="details-label">RESERVATION TYPE</span>
                            <strong>
                                {{ $booking->reservation_type === 'event' ? 'Events & Celebrations' : 'Public Pool and Cottages' }}
                            </strong>
                        </div>

                        @if ($booking->stay_type)
                            <div class="overview-item">
                                <span class="details-label">STAY TYPE</span>
                                <strong>{{ ucwords(str_replace('_', ' ', $booking->stay_type)) }}</strong>
                            </div>
                        @endif

                        <div class="overview-item">
                            <span class="details-label">CHECK-IN</span>
                            <strong>{{ $booking->check_in->format('F j, Y') }}</strong>
                        </div>

                        <div class="overview-item">
                            <span class="details-label">CHECK-OUT</span>
                            <strong>{{ $booking->check_out->format('F j, Y') }}</strong>
                        </div>

                        <div class="overview-item">
                            <span class="details-label">NUMBER OF GUESTS</span>
                            <strong>{{ $booking->guest_count }} Guests</strong>
                        </div>
                    </div>
                </div>

                {{-- BOOKING INFORMATION --}}
                <div class="details-section">
                    <div class="details-section-header">
                        <h2>Booking Information</h2>
                    </div>

                    <div class="booking-information">
                        @if ($booking->eventPackage)
                            <div class="information-row">
                                <span>Event Package</span>
                                <strong>{{ $booking->eventPackage->name }}</strong>
                            </div>
                        @endif

                        @if ($booking->cottages->isNotEmpty())
                            <div class="information-row">
                                <span>Selected Cottages</span>
                                <div class="cottage-list">
                                    @foreach ($booking->cottages as $cottage)
                                        <strong>{{ $cottage->name }}</strong>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($booking->additional_message)
                            <div class="information-row message-row">
                                <span>Additional Message</span>
                                <p>{{ $booking->additional_message }}</p>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- RIGHT COLUMN: PAYMENT INFORMATION --}}
            <div class="details-column">

                {{-- PAYMENT SUMMARY --}}
                <div class="details-section">
                    <div class="details-section-header">
                        <h2>Payment Summary</h2>

                        <span class="reservation-status payment-{{ strtolower($booking->payment_status) }}">
                            {{ ucfirst($booking->payment_status) }}
                        </span>
                    </div>

                    <div class="payment-summary">
                        <div class="payment-row">
                            <span>Total Reservation Cost</span>
                            <strong>₱{{ number_format($booking->total_price, 2) }}</strong>
                        </div>

                        <div class="payment-row">
                            <span>Reservation Fee</span>
                            <strong>₱{{ number_format($booking->reservation_fee, 2) }}</strong>
                        </div>

                        <div class="payment-row">
                            <span>Balance Payable Upon Arrival</span>
                            <strong>₱{{ number_format($booking->balance, 2) }}</strong>
                        </div>

                        <div class="payment-total">
                            <span>Amount to Pay Now</span>
                            <strong>₱{{ number_format($booking->reservation_fee, 2) }}</strong>
                        </div>
                    </div>

                    {{-- PAYMENT DEADLINE --}}
                    @if ($booking->status === 'pending' && $booking->payment_status !== 'paid' && $booking->expires_at)
                        <div class="payment-deadline">
                            <strong>Payment Deadline</strong>
                            <p>
                                Please pay your reservation fee by
                                {{ $booking->expires_at->format('M d, Y H:i') }}
                                ({{ config('app.timezone') }}).
                            </p>

                            @if ($booking->expires_at->lte(now()))
                                <p class="deadline-warning">
                                    The payment window has ended. Any existing checkout is being verified before the dates are released.
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- PAY NOW FORM --}}
                    @if ($booking->payment_status !== 'paid' && in_array($booking->status, ['pending', 'confirmed'], true) && (! $booking->expires_at || $booking->expires_at->isFuture()))

                        <div class="payment-action">
                            <h3>Complete Your Payment</h3>
                            <p>Select your preferred payment method to pay your reservation fee.</p>

                            <form
                                id="payment-form"
                                action="{{ route('payment.store', $booking) }}"
                                method="POST"
                            >
                                @csrf

                                <label for="payment-method">Payment Method</label>

                                <select id="payment-method" name="method" required>
                                    <option value="gcash">GCash</option>
                                    <option value="paymaya">Maya</option>
                                    <option value="qrph">QR Ph</option>
                                </select>

                                <button id="paylater-submit" type="submit">
                                    Pay ₱{{ number_format($booking->reservation_fee, 2) }} Now
                                </button>
                            </form>
                        </div>

                    @endif
                </div>

                {{-- RESERVATION STATUS MESSAGES --}}
                @if ($booking->status === 'expired')

                    <div class="reservation-notice notice-error">
                        <h3>Reservation Expired</h3>
                        <p>This unpaid reservation has expired. Please make a new reservation to choose available dates.</p>
                        <a href="{{ route('book') }}" class="details-primary-button">
                            Make a New Reservation
                        </a>
                    </div>

                @elseif ($booking->status === 'cancelled')

                    <div class="reservation-notice notice-error">
                        <h3>Reservation Cancelled</h3>
                        @if ($booking->cancellation_reason)
                            <p>Cancellation reason: {{ $booking->cancellation_reason }}</p>
                        @endif
                        @if ($booking->refund_status === 'pending_approval')
                            <p>Your recorded payment of ₱{{ number_format($booking->refund_amount, 2) }} is eligible for a refund and is awaiting the resort administrator's approval. No refund has been submitted yet.</p>
                        @elseif ($booking->refund_status === 'processing')
                            <p>Your refund of ₱{{ number_format($booking->refund_amount, 2) }} has been approved and is being verified or processed with PayMongo. The time before it appears in your account depends on the payment provider.</p>
                        @elseif ($booking->refund_status === 'refunded')
                            <p>PayMongo reports that your refund of ₱{{ number_format($booking->refund_amount, 2) }} is complete. Your payment provider may take additional time to display the credit.</p>
                        @elseif ($booking->refund_status === 'rejected')
                            <p>The resort administrator has disapproved your refund request.</p>
                            @if ($booking->refund_rejection_reason)
                                <p>Reason for disapproval: {{ $booking->refund_rejection_reason }}</p>
                            @endif
                        @elseif (in_array($booking->refund_status, ['failed', 'needs_review'], true))
                            <p>Your refund could not yet be confirmed. The resort administrator must check its PayMongo status. Contact the resort for an update.</p>
                        @elseif ($booking->refund_status === 'not_eligible')
                            <p>You cancelled more than 24 hours after paying the reservation fee. Your payment is non-refundable.</p>
                        @elseif ($booking->refund_status === 'not_required')
                            <p>No completed payment is recorded to refund. If you paid through an open checkout, contact the resort so it can verify the payment.</p>
                        @else
                            <p>This reservation has been cancelled. Contact the resort about any recorded payments.</p>
                        @endif
                    </div>

                @elseif ($booking->payment_status === 'paid')

                    <div class="reservation-notice notice-success">
                        <h3>Payment Completed</h3>

                        @if ($booking->checked_in_at)
                            <p>
                                You checked in on
                                {{ $booking->checked_in_at->format('M d, Y H:i') }}
                                ({{ config('app.timezone') }}).
                            </p>

                        @elseif ($canShowCheckInQr)
                            <div class="checkin-qr-section">
                                <h4>Your Check-in QR Code</h4>
                                <p>
                                    Present this QR code to resort staff on
                                    {{ $booking->check_in->format('F j, Y') }}.
                                    Keep it private.
                                </p>

                                <div class="checkin-qr-image">
                                    <img
                                        src="{{ route('reservations.qr', $booking) }}"
                                        width="280"
                                        height="280"
                                        alt="Check-in QR code for reservation {{ $booking->reference_code }}"
                                    >
                                </div>

                                <a href="{{ route('reservations.qr', ['booking' => $booking, 'download' => 1]) }}"
                                   class="details-primary-button">
                                    Download Check-in QR Code
                                </a>

                                @if ($booking->balance > 0)
                                    <p class="remaining-balance-note">
                                        Please settle your remaining balance at the resort before staff confirm check-in.
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                @else

                    <div class="reservation-notice notice-pending">
                        <h3>Payment Required</h3>
                        <p>Your reservation is not confirmed until the reservation fee is paid.</p>
                    </div>

                @endif

            </div>

        </div>

        @include('reservations.cancellation')

        {{-- FOOTER ACTION --}}
        <div class="details-footer">
            <a href="{{ route('home') }}" class="details-home-button">Back to Home</a>
        </div>

    </section>
@endsection
