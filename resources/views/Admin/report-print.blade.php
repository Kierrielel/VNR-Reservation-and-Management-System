<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collections report {{ $period['start_date'] }} to {{ $period['end_date'] }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin-reports.css') }}">
</head>
<body class="report-print">
    <div class="report-print-controls">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="{{ route('admin.reports.index', $period) }}">Back to reports</a>
    </div>
    <main>
        <h1>Collections report</h1>
        <p>{{ $period['start_date'] }} to {{ $period['end_date'] }} &middot; Generated {{ now()->format('M d, Y H:i') }} ({{ config('app.timezone') }})</p>
        <p><strong>Collected:</strong> ₱{{ number_format($summary['collected'], 2) }}</p>
        <p><strong>Guests with arrivals:</strong> {{ $summary['customers'] }} &middot; <strong>Reservations:</strong> {{ $summary['bookings'] }} &middot; <strong>Cancelled:</strong> {{ $summary['cancelled'] }}</p>
        <p>Payments use the date received; guest and reservation counts use arrival dates and exclude cancelled and expired bookings. Collections include money held for closed bookings. Refunds and expenses are not deducted.</p>
        <table class="report-table">
            <thead><tr><th scope="col">Date</th><th scope="col">Collected (PHP)</th></tr></thead>
            <tbody>
                @foreach ($chart['labels'] as $index => $date)
                    <tr><td>{{ $date }}</td><td>₱{{ number_format($chart['values'][$index], 2) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><th scope="row">Total</th><td>₱{{ number_format($summary['collected'], 2) }}</td></tr></tfoot>
        </table>
    </main>
</body>
</html>
