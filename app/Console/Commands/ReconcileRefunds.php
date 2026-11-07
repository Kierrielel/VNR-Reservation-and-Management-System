<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\RefundApprovalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('refunds:reconcile')]
#[Description('Check approved refund results with PayMongo without webhooks')]
class ReconcileRefunds extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RefundApprovalService $refunds): int
    {
        $checked = 0;
        $deferred = 0;
        $bookings = Booking::whereIn('refund_status', ['processing', 'needs_review'])
            ->orderBy('updated_at')->limit(100)->get();
        foreach ($bookings as $booking) {
            try {
                $refunds->reconcile($booking);
                $checked++;
            } catch (Throwable $exception) {
                Log::warning('Refund reconciliation deferred', ['booking_id' => $booking->id, 'exception' => $exception::class]);
                $deferred++;
            }
        }
        $this->info("Refunds checked: {$checked}; deferred: {$deferred}.");

        return self::SUCCESS;
    }
}
