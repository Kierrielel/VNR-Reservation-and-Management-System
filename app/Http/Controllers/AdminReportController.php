<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportPeriodRequest;
use App\Services\ReportingService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(ReportPeriodRequest $request, ReportingService $reporting): View
    {
        return view('Admin.dashboard', $this->reportData($request, $reporting));
    }

    public function printable(ReportPeriodRequest $request, ReportingService $reporting): View
    {
        return view('Admin.report-print', $this->reportData($request, $reporting));
    }

    public function export(ReportPeriodRequest $request, ReportingService $reporting): StreamedResponse
    {
        $period = $request->validated();

        return response()->streamDownload(function () use ($period, $reporting): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Payment ID', 'Paid date', 'Booking reference', 'Guest', 'Method', 'Amount (PHP)', 'Booking status', 'Transaction reference'], escape: '');

            foreach ($reporting->collectedPayments($period['start_date'], $period['end_date'])->with('booking.user')->lazyById(500) as $payment) {
                $row = [
                    (string) $payment->id,
                    ($payment->paid_at ?? $payment->updated_at ?? $payment->created_at)->format('Y-m-d H:i:s'),
                    $payment->booking?->reference_code ?? '',
                    $payment->booking?->guest_display_name ?? 'Guest',
                    $payment->method,
                    number_format((float) $payment->amount, 2, '.', ''),
                    $payment->booking?->status ?? '',
                    $payment->reference_no ?? '',
                ];
                fputcsv($output, array_map($this->csvCell(...), $row), escape: '');
            }

            fclose($output);
        }, 'payments-'.$period['start_date'].'-to-'.$period['end_date'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function reportData(ReportPeriodRequest $request, ReportingService $reporting): array
    {
        $period = $request->validated();

        return [
            'period' => $period,
            'summary' => $reporting->summary($period['start_date'], $period['end_date']),
            'chart' => $reporting->dailyRevenue($period['start_date'], $period['end_date']),
        ];
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'".$value : $value;
    }
}
