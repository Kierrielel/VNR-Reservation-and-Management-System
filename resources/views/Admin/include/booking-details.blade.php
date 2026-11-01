<section class="admin-bookings">
    <a href="{{ route('admin.bookings.index') }}" class="booking-back">&larr; Back to bookings</a>
    <div class="bookings-header">
        <h1>Booking {{ $booking->reference_code ?? '#'.$booking->id }}</h1>
    </div>

    @include('Admin.include.booking-alerts')

    <div class="booking-detail-grid">
        <section class="booking-detail-card">
            <h2>Reservation details</h2>
            <dl class="booking-data">
                <dt>Guest</dt><dd>{{ $booking->guest_display_name }}</dd>
                <dt>Email</dt><dd>{{ $booking->guest_display_email ?? 'Not provided' }}</dd>
                @if ($booking->guest_name !== null)
                    <dt>Contact</dt><dd>{{ $booking->guest_contact }}</dd>
                    <dt>Walk-in recorded by</dt><dd>{{ $booking->bookedBy?->name ?? 'Former staff member' }}</dd>
                    <dt>Walk-in actions</dt><dd><a href="{{ route('walk-ins.show', $booking) }}">Payment / arrival / QR</a></dd>
                @endif
                <dt>Type</dt><dd>{{ $booking->reservation_type === 'event' ? 'Event' : 'Public pool' }}</dd>
                <dt>Stay</dt><dd>{{ $booking->stay_type ? ucwords(str_replace('_', ' ', $booking->stay_type)) : 'Event day' }}</dd>
                <dt>Arrival</dt><dd>{{ $booking->check_in->format('M d, Y') }}</dd>
                <dt>Departure</dt><dd>{{ $booking->check_out->format('M d, Y') }}</dd>
                <dt>Guests</dt><dd>{{ $booking->guest_count }}</dd>
                <dt>Accommodation</dt>
                <dd>{{ $booking->eventPackage?->name ?? ($booking->cottages->pluck('name')->join(', ') ?: 'None') }}</dd>
                <dt>Status</dt><dd>{{ ucfirst($booking->status) }}</dd>
                @if ($booking->checked_in_at)
                    <dt>Checked in</dt><dd>{{ $booking->checked_in_at->format('M d, Y H:i') }} ({{ config('app.timezone') }})</dd>
                    <dt>Checked in by</dt><dd>{{ $booking->checkedInBy?->name ?? 'Former staff member' }}</dd>
                @endif
                @if ($booking->expires_at)
                    <dt>Payment deadline</dt><dd>{{ $booking->expires_at->format('M d, Y H:i') }} ({{ config('app.timezone') }})</dd>
                @endif
            </dl>
            @if ($booking->additional_message)
                <h3>Guest message</h3>
                <p class="booking-message">{{ $booking->additional_message }}</p>
            @endif
        </section>

        <section class="booking-detail-card">
            <h2>Payment summary</h2>
            <dl class="booking-data">
                <dt>Total price</dt><dd>₱{{ number_format($booking->total_price, 2) }}</dd>
                <dt>Reservation fee</dt><dd>₱{{ number_format($booking->reservation_fee, 2) }}</dd>
                <dt>Fee status</dt><dd>{{ $booking->payment_status === 'paid' ? 'Paid' : ucfirst($booking->payment_status) }}</dd>
                <dt>Recorded payments</dt><dd>₱{{ number_format($booking->payments->where('status', 'paid')->sum('amount'), 2) }}</dd>
                <dt>Balance after fee</dt><dd>₱{{ number_format($booking->balance, 2) }}</dd>
            </dl>

            @if ($booking->status === 'confirmed' && $booking->payment_status === 'paid' && $booking->balance > 0)
                <h3>Record remaining balance</h3>
                <p>Record the full ₱{{ number_format($booking->balance, 2) }} only after receiving it from the guest.</p>
                <form method="POST" action="{{ route('admin.bookings.balance', $booking) }}" class="booking-payment-form">
                    @csrf
                    <label for="balance-method">Payment method</label>
                    <select id="balance-method" name="method" required>
                        @foreach (['cash' => 'Cash', 'gcash' => 'GCash', 'paymaya' => 'Maya', 'qrph' => 'QR Ph'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label for="balance-reference">Receipt / transaction reference (required for digital payments)</label>
                    <input id="balance-reference" name="reference_no" value="{{ old('reference_no') }}" maxlength="100">
                    <button type="submit" class="booking-button">Record balance payment</button>
                </form>
            @endif
        </section>
    </div>

    <section class="booking-detail-card booking-status-card">
        <h2>Manage booking</h2>
        <div class="booking-status-actions">
            @if ($booking->status === 'pending' && $booking->payment_status === 'paid')
                <form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="confirmed">
                    <button class="booking-button" type="submit">Confirm booking</button>
                </form>
            @endif
            @if ($booking->status === 'confirmed' && $booking->payment_status === 'paid' && $booking->balance <= 0 && ! $booking->check_out->isAfter(today()))
                <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" data-confirm
                    data-confirm-title="Complete booking?" data-confirm-message="Mark this reservation as completed after the guest has departed."
                    data-confirm-label="Yes, complete">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="completed">
                    <button class="booking-button" type="submit">Complete booking</button>
                </form>
            @endif
            @if (in_array($booking->status, ['pending', 'confirmed'], true) && ! $booking->checked_in_at)
                <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" data-confirm
                    data-confirm-title="Cancel booking?" data-confirm-message="This releases the reserved dates. Payment records are kept; refunds are handled separately."
                    data-confirm-label="Yes, cancel booking">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="cancelled">
                    <button class="booking-button booking-button-danger" type="submit">Cancel booking</button>
                </form>
            @elseif ($booking->status === 'cancelled')
                <p>This booking is cancelled. Payment records are retained; no automatic refund has been issued.</p>
            @elseif ($booking->status === 'expired')
                <p>This unpaid reservation expired and its dates have been released. Records are retained.</p>
            @elseif ($booking->checked_in_at && $booking->status !== 'completed')
                <p>The guest is checked in. Complete the booking after departure.</p>
            @else
                <p>This booking is completed.</p>
            @endif
        </div>
    </section>

    <section class="booking-detail-card">
        <h2>Payment history</h2>
        <div class="booking-history-scroll">
            <table class="booking-history">
                <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
                <tbody>
                    @forelse ($booking->payments->sortByDesc('id') as $payment)
                        <tr>
                            <td>{{ ($payment->paid_at ?? $payment->created_at)->format('M d, Y H:i') }}</td>
                            <td>{{ strtoupper($payment->method) }}</td>
                            <td>₱{{ number_format($payment->amount, 2) }}</td>
                            <td>{{ ucfirst($payment->status) }}</td>
                            <td>{{ $payment->reference_no ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No payment records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
