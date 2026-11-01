@extends('LoginReg.layout')

@section('title', 'My Reservations | Villa Natividad')

@section('content')
    <section class="reservation-summary">
        <h1>My Reservations</h1>

        @if (session('success'))
            <p class="alert-success">{{ session('success') }}</p>
        @endif

        @if ($bookings->isEmpty())
            <p>You don't have any reservations yet.</p>
            <a href="{{ route('book') }}">Book your first stay</a>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Type</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Guests</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $booking)
                        <tr>
                            <td>{{ $booking->reference_code }}</td>
                            <td>{{ $booking->reservation_type === 'event' ? 'Event' : 'Public Pool' }}</td>
                            <td>{{ $booking->check_in->format('M j, Y') }}</td>
                            <td>{{ $booking->check_out->format('M j, Y') }}</td>
                            <td>{{ $booking->guest_count }}</td>
                            <td>₱{{ number_format($booking->total_price, 2) }}</td>
                            <td>{{ ucfirst($booking->status) }}</td>
                            <td>{{ ucfirst($booking->payment_status) }}</td>
                            <td>
                                <a href="{{ route('reservations.show', $booking) }}">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
