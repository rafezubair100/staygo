<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record when a booking was cancelled and what the supplier
     * charged and refunded.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('payment_status');
            $table->decimal('cancellation_fee', 12, 2)->nullable()->after('cancelled_at');
            $table->decimal('refund_amount', 12, 2)->nullable()->after('cancellation_fee');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_fee', 'refund_amount']);
        });
    }
};