@extends('LoginReg.layout')

@section('title', 'Reservation ' . $booking->reference_code . ' | Villa Natividad')

@section('content')
    <section class="reservation-summary">
        <h1>Reservation Summary</h1>

        @if (session('success'))
            <p class="alert-success">{{ session('success') }}</p>
        @endif

        @if (session('error'))
            <p class="alert-danger">{{ session('error') }}</p>
        @endif

        <p>Reference code: <strong>{{ $booking->reference_code }}</strong></p>
        <p>Status: {{ ucfirst($booking->status) }} | Payment: {{ ucfirst($booking->payment_status) }}</p>
        @if ($booking->status === 'pending' && $booking->payment_status !== 'paid' && $booking->expires_at)
            <p>Pay the reservation fee by {{ $booking->expires_at->format('M d, Y H:i') }} ({{ config('app.timezone') }}) to keep your dates.</p>
            @if ($booking->expires_at->lte(now()))
                <p>The payment window has ended. Any existing checkout is being verified before the dates are released.</p>
            @endif
        @endif

        <h2>Details</h2>
        <ul>
            <li>
                Type:
                {{ $booking->reservation_type === 'event' ? 'Events & Celebrations' : 'Public Pool and Cottages' }}
            </li>

            @if ($booking->stay_type)
                <li>Stay type: {{ ucwords(str_replace('_', ' ', $booking->stay_type)) }}</li>
            @endif

            <li>Check-in: {{ $booking->check_in->format('F j, Y') }}</li>
            <li>Check-out: {{ $booking->check_out->format('F j, Y') }}</li>
            <li>Guests: {{ $booking->guest_count }}</li>

            @if ($booking->eventPackage)
                <li>Package: {{ $booking->eventPackage->name }}</li>
            @endif

            @foreach ($booking->cottages as $cottage)
                <li>Cottage: {{ $cottage->name }}</li>
            @endforeach

            @if ($booking->additional_message)
                <li>Message: {{ $booking->additional_message }}</li>
            @endif
        </ul>

        <h2>Payment</h2>
        <ul>
            <li>Total: ₱{{ number_format($booking->total_price, 2) }}</li>
            <li>Reservation fee: ₱{{ number_format($booking->reservation_fee, 2) }}</li>
            <li>Balance payable upon arrival: ₱{{ number_format($booking->balance, 2) }}</li>
        </ul>
        @if ($booking->payment_status !== 'paid' && in_array($booking->status, ['pending', 'confirmed'], true) && (! $booking->expires_at || $booking->expires_at->isFuture()))
        <form
            id="payment-form"
            action="{{ route('payment.store', $booking) }}"
            method="POST"
        >
            @csrf
            <label for="payment-method">Payment method</label>
            <select id="payment-method" name="method" required>
                <option value="gcash">GCash</option>
                <option value="paymaya">Maya</option>
                <option value="qrph">QR Ph</option>
            </select>

            <button id="paylater-submit" type="submit">Pay now</button>
        </form>
        @endif
        @if ($booking->status === 'expired')
            <p>This unpaid reservation expired. Please make a new reservation to choose available dates.</p>
            <a href="{{ route('book') }}">Make a new reservation</a>
        @elseif ($booking->status === 'cancelled')
            <p>This reservation has been cancelled. Contact the resort about any recorded payments.</p>
        @elseif ($booking->payment_status === 'paid')
            @if ($booking->checked_in_at)
                <p>Checked in on {{ $booking->checked_in_at->format('M d, Y H:i') }} ({{ config('app.timezone') }}).</p>
            @elseif ($canShowCheckInQr)
                <section aria-labelledby="reservation-checkin-title">
                    <h2 id="reservation-checkin-title">Your check-in QR code</h2>
                    <p>Present this code to resort staff on {{ $booking->check_in->format('F j, Y') }}. Keep it private.</p>
                    <img src="{{ route('reservations.qr', $booking) }}" width="280" height="280" alt="Check-in QR code for reservation {{ $booking->reference_code }}">
                    <p><a href="{{ route('reservations.qr', ['booking' => $booking, 'download' => 1]) }}">Download check-in QR code</a></p>
                    @if ($booking->balance > 0)
                        <p>Please settle your remaining balance at the resort before staff confirm check-in.</p>
                    @endif
                </section>
            @endif
        @else
            <p>Your reservation is not confirmed until the reservation fee is paid.</p>
        @endif

        <a href="{{ route('home') }}">Back to home</a>
    </section>
@endsection
