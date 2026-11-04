
@extends('LoginReg.layout')

@section('title', 'My Reservations | Villa Natividad')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/my-reservations.css') }}">
@endpush

@section('content')
    <section class="reservation-summary">

        <div class="reservation-header">
            <div>
                <h1>My Reservations</h1>
                <p>Keep track of your bookings and reservation details.</p>
            </div>

            @if (!$bookings->isEmpty())
                <a href="{{ route('book') }}" class="reservation-book-button">
                    Make a Reservation
                </a>
            @endif
        </div>

        @if (session('success'))
            <p class="reservation-alert-success">
                {{ session('success') }}
            </p>
        @endif

        @if ($bookings->isEmpty())
            <div class="reservation-empty">
                <h2>No Reservations Yet</h2>
                <p>You haven't made any reservations with us. Start planning your visit today!</p>

                <a href="{{ route('book') }}" class="reservation-book-button">
                    Book Your First Stay
                </a>
            </div>
        @else
            <div class="reservation-table-container">
                <table class="reservation-table">
                    <thead>
                        <tr>
                            <th>REFERENCE</th>
                            <th>TYPE</th>
                            <th>CHECK-IN</th>
                            <th>CHECK-OUT</th>
                            <th>GUESTS</th>
                            <th>TOTAL</th>
                            <th>STATUS</th>
                            <th>PAYMENT</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($bookings as $booking)
                            <tr>
                                <td>
                                    <span class="reservation-reference">
                                        {{ $booking->reference_code }}
                                    </span>
                                </td>

                                <td>
                                    {{ $booking->reservation_type === 'event' ? 'Event' : 'Public Pool' }}
                                </td>

                                <td>{{ $booking->check_in->format('M j, Y') }}</td>
                                <td>{{ $booking->check_out->format('M j, Y') }}</td>
                                <td>{{ $booking->guest_count }}</td>

                                <td class="reservation-total">
                                    ₱{{ number_format($booking->total_price, 2) }}
                                </td>

                                <td>
                                    <span class="reservation-status status-{{ strtolower($booking->status) }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="reservation-status payment-{{ strtolower($booking->payment_status) }}">
                                        {{ ucfirst($booking->payment_status) }}
                                    </span>
                                </td>

                                <td>
                                    <a href="{{ route('reservations.show', $booking) }}"
                                       class="reservation-view-button">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </section>
@endsection
