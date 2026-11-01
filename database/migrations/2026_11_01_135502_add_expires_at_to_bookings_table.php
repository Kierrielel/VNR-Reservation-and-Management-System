<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable();
            $table->index(['status', 'payment_status', 'expires_at']);
        });

        DB::table('bookings')->where('status', 'pending')->where('payment_status', '!=', 'paid')
            ->whereNotNull('created_at')->orderBy('id')->chunkById(200, function ($bookings): void {
                foreach ($bookings as $booking) {
                    DB::table('bookings')->where('id', $booking->id)->update([
                        'expires_at' => Carbon::parse($booking->created_at)->addMinutes(config('reservations.hold_minutes'))->toDateTimeString(),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_status', 'expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
