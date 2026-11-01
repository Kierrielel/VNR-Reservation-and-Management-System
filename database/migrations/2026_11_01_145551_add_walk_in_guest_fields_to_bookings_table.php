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
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_name')->nullable();
            $table->string('guest_contact', 50)->nullable();
            $table->string('guest_email')->nullable();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('bookings')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Walk-in bookings must be retained. Use a forward migration instead of rolling back this change.');
        }
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booked_by');
            $table->dropColumn(['guest_name', 'guest_contact', 'guest_email']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
