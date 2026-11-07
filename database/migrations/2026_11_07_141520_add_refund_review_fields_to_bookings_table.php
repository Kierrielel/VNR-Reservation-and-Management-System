<?php

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
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('refund_decided_at')->nullable();
            $table->foreignId('refund_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('refund_rejection_reason')->nullable();
            $table->index(['refund_status', 'updated_at']);
        });
        DB::table('bookings')->where('refund_status', 'pending_manual')->update(['refund_status' => 'pending_approval']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_decided_by');
            $table->dropIndex(['refund_status', 'updated_at']);
            $table->dropColumn(['cancellation_reason', 'refund_decided_at', 'refund_rejection_reason']);
        });
        DB::table('bookings')->where('refund_status', 'pending_approval')->update(['refund_status' => 'pending_manual']);
    }
};
