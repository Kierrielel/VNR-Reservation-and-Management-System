<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>QR Check-in | Villa Natividad</title>
    <link rel="stylesheet" href="{{ asset('css/check-in.css') }}">
    @vite('resources/js/check-in.js')
</head>
<body>
    <header class="checkin-header">
        <div><strong>Villa Natividad</strong><span>Arrival check-in</span></div>
        <nav aria-label="Staff navigation">
            <a href="{{ route('walk-ins.create') }}">Walk-in Booking</a>
            @if (auth()->user()->role === 'admin')
                <a href="{{ route('admin.dashboard') }}">Admin dashboard</a>
            @endif
            <span>{{ auth()->user()->name }}</span>
            <a href="{{ route('logout') }}">Log out</a>
        </nav>
    </header>
    <main id="checkin-app" data-preview-url="{{ route('check-in.preview') }}" data-confirm-url="{{ route('check-in.store') }}">
        <h1>QR Check-in</h1>
        <p>Scan the guest's QR code, verify their reservation, then confirm arrival.</p>
        <p id="scan-status" role="status" aria-live="polite">Ready to scan.</p>

        <div class="checkin-grid">
            <section class="checkin-card" aria-labelledby="scanner-heading">
                <h2 id="scanner-heading">Scan a reservation</h2>
                <div class="checkin-video"><video id="checkin-video" muted playsinline aria-label="QR camera preview"></video></div>
                <div class="checkin-actions">
                    <button id="start-camera" type="button">Start camera</button>
                    <button id="stop-camera" type="button" disabled>Stop camera</button>
                </div>
                <p>Camera access requires HTTPS or localhost. You can also scan a saved QR image below.</p>
                <label for="qr-file">Scan from an image</label>
                <input id="qr-file" type="file" accept="image/*">
                <form id="qr-text-form">
                    <label for="qr-text">Paste a check-in code or use a USB scanner</label>
                    <textarea id="qr-text" maxlength="2048" rows="3" required autocomplete="off"></textarea>
                    <button type="submit">Review reservation</button>
                </form>
            </section>

            <section class="checkin-card" aria-labelledby="reservation-heading">
                <h2 id="reservation-heading">Reservation review</h2>
                <p id="review-placeholder">Scan a code to see the guest's booking.</p>
                <div id="reservation-review" hidden>
                    <dl id="reservation-fields"></dl>
                    <p id="balance-message"></p>
                    <a id="manage-booking" hidden>Open booking / record balance payment</a>
                    <div class="checkin-actions">
                        <button id="confirm-checkin" type="button" disabled>Confirm check-in</button>
                        <button id="reset-checkin" type="button">Clear review</button>
                    </div>
                </div>
            </section>
        </div>

        <section class="checkin-card">
            <h2>Recent walk-in bookings</h2>
            <ul>
                @forelse ($walkIns as $walkIn)
                    <li><a href="{{ route('walk-ins.show', $walkIn) }}">{{ $walkIn->reference_code }} — {{ $walkIn->guest_display_name }}</a> ({{ ucfirst($walkIn->status) }}, {{ $walkIn->check_in->format('M d, Y') }})</li>
                @empty
                    <li>No walk-in bookings yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="checkin-card">
            <h2>Latest check-ins today</h2>
            <div class="checkin-table-wrap">
                <table>
                    <thead><tr><th>Guest</th><th>Reference</th><th>Checked in</th><th>Staff member</th></tr></thead>
                    <tbody>
                        @forelse ($arrivals as $arrival)
                            <tr>
                                <td>{{ $arrival->guest_display_name }}</td>
                                <td>{{ $arrival->reference_code ?? '#'.$arrival->id }}</td>
                                <td>{{ $arrival->checked_in_at->format('H:i') }}</td>
                                <td>{{ $arrival->checkedInBy?->name ?? 'Former staff member' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No check-ins yet today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p>Times are shown in {{ config('app.timezone') }}. <a href="{{ route('check-in.index') }}">Refresh arrivals</a></p>
        </section>
    </main>
</body>
</html>
