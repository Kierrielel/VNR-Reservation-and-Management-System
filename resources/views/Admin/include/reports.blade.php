<section class="admin-reports">
    <div class="reports-header"><h1>Revenue Analytics</h1></div>
    @if ($errors->any())
        <div class="report-error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="GET" action="{{ route('admin.reports.index') }}" class="report-filters">
        <label for="report-start">From
            <input id="report-start" type="date" name="start_date" value="{{ $period['start_date'] }}" required>
        </label>
        <label for="report-end">To
            <input id="report-end" type="date" name="end_date" value="{{ $period['end_date'] }}" required>
        </label>
        <button type="submit">Apply dates</button>
        <a href="{{ route('admin.reports.index') }}">This month</a>
    </form>
    <p class="report-definition">Payment totals use the date received. Guest and reservation counts use arrival dates and exclude cancelled and expired bookings.</p>
    <div class="report-summary">
        <div class="report-card customers-card">
            <p>Guests with arrivals</p>
            <h2 id="customerCount">{{ $summary['customers'] }}</h2>
            <span>{{ $summary['bookings'] }} reservations &middot; {{ $summary['cancelled'] }} cancelled</span>
        </div>
        <div class="report-card revenue-card">
            <div>
                <p>Collected payments</p>
                <h2 id="revenueCount">₱{{ number_format($summary['collected'], 2) }}</h2>
                <span>{{ $period['start_date'] }} to {{ $period['end_date'] }}</span>
            </div>
        </div>
    </div>
    <div class="report-buttons report-export-actions">
        <a href="{{ route('admin.reports.export', $period) }}">Download CSV (Excel)</a>
        <a href="{{ route('admin.reports.print', $period) }}" target="_blank" rel="noopener">Print / Save as PDF</a>
    </div>
    <p class="report-definition">Collected payments include money held for closed bookings. Refunds and expenses are not deducted.</p>
    <div class="revenue-chart-container">
        <div class="chart-container">
            <canvas id="revenueChart" data-labels="{{ json_encode($chart['labels']) }}" data-values="{{ json_encode($chart['values']) }}" aria-label="Collected payments for the selected period" role="img"></canvas>
        </div>
    </div>
    <div class="report-table-container">
        <table class="report-table">
            <caption>Daily collections</caption>
            <thead><tr><th scope="col">Date</th><th scope="col">Collected (PHP)</th></tr></thead>
            <tbody>
                @foreach ($chart['labels'] as $index => $date)
                    <tr><td>{{ $date }}</td><td>₱{{ number_format($chart['values'][$index], 2) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><th scope="row">Total</th><td>₱{{ number_format($summary['collected'], 2) }}</td></tr></tfoot>
        </table>
    </div>
</section>

