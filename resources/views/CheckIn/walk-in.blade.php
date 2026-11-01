<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Walk-in booking | Villa Natividad</title>
    <link rel="stylesheet" href="{{ asset('css/check-in.css') }}">
</head>
<body>
    <header class="checkin-header">
        <strong>Villa Natividad — Walk-in booking</strong>
        <nav aria-label="Staff navigation">
            <a href="{{ route('walk-ins.create') }}">New walk-in</a>
            <a href="{{ route('check-in.index') }}">QR Check-in</a>
            @if (auth()->user()->role === 'admin')
                <a href="{{ route('admin.bookings.show', $booking) }}">Manage booking</a>
            @endif
        </nav>
    </header>
    <main>
        @if (session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <section class="checkin-card">
            <h1>{{ $booking->reference_code }}</h1>
            <dl>
                <dt>Guest</dt><dd>{{ $booking->guest_display_name }}</dd>
                <dt>Contact number</dt><dd>{{ $booking->guest_contact }}</dd>
                <dt>Email</dt><dd>{{ $booking->guest_display_email ?? 'Not provided' }}</dd>
                <dt>Created by</dt><dd>{{ $booking->bookedBy?->name ?? 'Former staff member' }}</dd>
                <dt>Arrival / departure</dt><dd>{{ $booking->check_in->format('M d, Y') }} / {{ $booking->check_out->format('M d, Y') }}</dd>
                <dt>Guests</dt><dd>{{ $booking->guest_count }}</dd>
                <dt>Accommodation</dt><dd>{{ $booking->eventPackage?->name ?? $booking->cottages->pluck('name')->join(', ') }}</dd>
                <dt>Status</dt><dd>{{ ucfirst($booking->status) }}</dd>
                <dt>Total price</dt><dd>₱{{ number_format($booking->total_price, 2) }}</dd>
                <dt>Recorded payments</dt><dd>₱{{ number_format($booking->payments->where('status', 'paid')->sum('amount'), 2) }}</dd>
            </dl>
            @if ($booking->status === 'pending' && $booking->payment_status === 'unpaid')
                <p>Receive the full ₱{{ number_format($booking->total_price, 2) }} in cash before confirming below.</p>
                <p>Payment deadline: {{ $booking->expires_at?->format('M d, Y H:i') }} ({{ config('app.timezone') }}).</p>
                <form action="{{ route('walk-ins.payment', $booking) }}" method="POST">
                    @csrf
                    <label><input type="checkbox" name="payment_received" value="1" required> I received the full cash payment from the guest.</label>
                    <button type="submit">Record full cash payment</button>
                </form>
            @endif
            @if ($booking->checked_in_at)
                <p>Checked in at {{ $booking->checked_in_at->format('M d, Y H:i') }} by {{ $booking->checkedInBy?->name ?? 'Former staff member' }}.</p>
            @elseif ($canShowQr)
                <img src="{{ route('walk-ins.qr', $booking) }}" alt="Guest check-in QR code" width="280" height="280">
                <p><a href="{{ route('walk-ins.qr', [$booking, 'download' => 1]) }}">Download check-in QR</a></p>
                @if ($booking->check_in->isToday())
                    <form action="{{ route('walk-ins.check-in', $booking) }}" method="POST">
                        @csrf
                        <button type="submit">Confirm guest arrival</button>
                    </form>
                @endif
            @endif
        </section>
    </main>
</body>
</html>
