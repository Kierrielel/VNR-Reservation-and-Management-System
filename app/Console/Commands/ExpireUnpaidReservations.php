<?php

namespace App\Console\Commands;

use App\Services\BookingExpiryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:expire')]
#[Description('Verify payments and release expired unpaid reservation holds')]
class ExpireUnpaidReservations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BookingExpiryService $expiry): int
    {
        $counts = $expiry->expireDue();
        $this->info("Expired: {$counts['expired']}; confirmed: {$counts['confirmed']}; deferred: {$counts['deferred']}.");

        return self::SUCCESS;
    }
}
