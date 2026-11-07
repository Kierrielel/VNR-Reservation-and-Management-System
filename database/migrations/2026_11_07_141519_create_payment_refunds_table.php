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
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_refund_id')->nullable()->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->string('notes', 255);
            $table->decimal('amount', 12, 2);
            $table->string('status', 32)->default('queued');
            $table->string('error', 255)->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->index(['booking_id', 'status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
