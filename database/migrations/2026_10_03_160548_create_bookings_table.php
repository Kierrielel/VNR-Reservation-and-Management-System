<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_package_id')->nullable()->constrained('event_packages')->nullOnDelete();

            $table->string('reservation_type'); // 'public' or 'event'
            $table->string('stay_type')->nullable(); // 'day_tour' or 'overnight' (null for events)

            $table->date('check_in');
            $table->date('check_out');

            $table->unsignedInteger('guest_count');

            $table->decimal('total_price', 10, 2);
            $table->decimal('reservation_fee', 10, 2);
            $table->decimal('balance', 10, 2);

            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, failed
            $table->string('payment_method')->nullable(); // gcash, maya, qr_ph

            $table->string('reference_code')->unique()->nullable(); // for the QR code / reference ID
            $table->text('additional_message')->nullable(); // from your "Tell us more about your visit" field

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
