@if ($canCancelReservation)
    <section class="details-section">
        <div class="details-section-header"><h2>Cancel Reservation</h2></div>
        <p>You can cancel before check-in. Paid reservations qualify for a refund only within 24 hours of paying the reservation fee. Later cancellations are non-refundable because the resort has reserved the accommodation.</p>
        @if ($refundPreview['deadline'])
            <p>Refund deadline: {{ $refundPreview['deadline']->format('M d, Y H:i:s') }} ({{ config('app.timezone') }}).</p>
        @endif
        @if ($refundPreview['status'] === 'pending_approval')
            <p>If you cancel now, ₱{{ number_format($refundPreview['amount'], 2) }} qualifies for refund review. The admin must approve the request before it is submitted to PayMongo.</p>
        @elseif ($refundPreview['status'] === 'not_eligible')
            <p><strong>The 24-hour refund window has ended. If you cancel now, you will not receive a refund.</strong></p>
        @else
            <p>No completed payment is recorded to refund.</p>
        @endif
        <form action="{{ route('reservations.cancel', $booking) }}" method="POST">
            @csrf
            <label for="cancellation_reason">Reason for cancellation</label>
            <textarea id="cancellation_reason" name="cancellation_reason" rows="3" maxlength="1000" required>{{ old('cancellation_reason') }}</textarea>
            <label>
                <input type="checkbox" name="confirm_cancellation" value="1" required>
                I understand the refund policy and want to permanently cancel this reservation.
            </label>
            <button type="submit" class="details-primary-button">Cancel Reservation</button>
        </form>
    </section>
@endif
