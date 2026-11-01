<section class="admin-bookings">

    <div class="bookings-header bookings-header-with-actions">
        <h1>Booking Management</h1>
        <a href="{{ route('walk-ins.create') }}" class="booking-button">Walk-in Booking</a>
        <a href="{{ route('check-in.index') }}" class="booking-button">QR Check-in</a>
    </div>

    @include('Admin.include.booking-alerts')

    <form method="GET" action="{{ route('admin.bookings.index') }}" class="booking-filters">
        <label>Guest or reference
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, email, or reference" maxlength="100">
        </label>
        <label>Status
            <select name="status">
                <option value="">All statuses</option>
                @foreach (['pending', 'confirmed', 'completed', 'cancelled', 'expired'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>
        <label>Arrival date<input type="date" name="date" value="{{ request('date') }}"></label>
        <label>Type
            <select name="type">
                <option value="">All types</option>
                <option value="public" @selected(request('type') === 'public')>Public pool</option>
                <option value="event" @selected(request('type') === 'event')>Event</option>
            </select>
        </label>
        <button type="submit" class="booking-button">Filter</button>
        <a href="{{ route('admin.bookings.index') }}" class="booking-button booking-button-secondary">Reset</a>
    </form>

    <div class="bookings-table-container">

        <table class="bookings-table">

            <thead>
                <tr>
                    <th>GUEST NAME</th>
                    <th>DATE</th>
                    <th>TYPE</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($recentBooking as $booking)
                    <tr>
                        <td>
                            {{ $booking->guest_display_name }}
                            <small class="booking-reference">{{ $booking->reference_code ?? 'Booking #'.$booking->id }}</small>
                        </td>
                        <td>
                            @if ($booking->check_in)
                                {{ \Carbon\Carbon::parse($booking->check_in)->format('M d, Y') }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td>{{ ucfirst($booking->reservation_type) }}</td>
                        <td>
                            <span class="dashboard-status-badge status-{{ strtolower($booking->status) }}">
                                {{ ucfirst($booking->status) }}
                            </span>
                        </td>
                        <td>
                            <div class="booking-actions">
                                <a href="{{ route('admin.bookings.show', $booking) }}" class="booking-details-link"
                                    aria-label="View booking {{ $booking->reference_code ?? $booking->id }}">Details</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 20px; color: #888;">
                            No recent bookings
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>

        <div class="bookings-footer">

            <span class="booking-results">
                Showing {{ $recentBooking->firstItem() ?? 0 }} to {{ $recentBooking->lastItem() ?? 0 }} of {{ $recentBooking->total() }} results
            </span>

            {{ $recentBooking->onEachSide(1)->links('Admin.include.booking-pagination') }}

        </div>

    </div>

</section>
