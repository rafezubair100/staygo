<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let the bookings table store bookings for live supplier hotels,
     * which have no record in the local hotels table.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Live hotels are not stored locally, so the link becomes optional.
            $table->unsignedBigInteger('hotel_id')->nullable()->change();

            $table->string('supplier_hotel_id')->nullable()->index()->after('supplier_reference');
            $table->string('hotel_name')->nullable()->after('supplier_hotel_id');
            $table->string('room_name')->nullable()->after('hotel_name');
            $table->string('board')->nullable()->after('room_name');
            $table->string('hotel_confirmation_code')->nullable()->after('board');
            $table->string('client_reference')->nullable()->unique()->after('hotel_confirmation_code');
            $table->boolean('refundable')->default(false)->after('payment_status');
            $table->boolean('is_sandbox')->default(false)->after('refundable');
            $table->string('guest_phone', 40)->nullable()->after('guest_email');
        });
    }

    /**
     * Remove the added columns.
     *
     * hotel_id is left optional, because live bookings stored without
     * a local hotel could not be kept if it became required again.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['client_reference']);
            $table->dropIndex(['supplier_hotel_id']);

            $table->dropColumn([
                'supplier_hotel_id',
                'hotel_name',
                'room_name',
                'board',
                'hotel_confirmation_code',
                'client_reference',
                'refundable',
                'is_sandbox',
                'guest_phone',
            ]);
        });
    }
};