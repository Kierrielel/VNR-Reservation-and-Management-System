@extends('LoginReg.layout')

@section('title', 'Reservation ' . $booking->reference_code . ' | Villa Natividad')

@section('content')
    <section class="reservation-summary">
        <h1>Reservation Summary</h1>

        @if (session('success'))
            <p class="alert-success">{{ session('success') }}</p>
        @endif

        <p>Reference code: <strong>{{ $booking->reference_code }}</strong></p>
        <p>Status: {{ ucfirst($booking->status) }} | Payment: {{ ucfirst($booking->payment_status) }}</p>

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

        @if ($booking->payment_status === 'paid')
            {{-- QR code goes here once the payment flow exists. Show it only when paid. --}}
            <p>Present this reference code at check-in: {{ $booking->reference_code }}</p>
        @else
            <p>Your reservation is not confirmed until the reservation fee is paid.</p>
        @endif

        <a href="{{ route('home') }}">Back to home</a>
    </section>
@endsection