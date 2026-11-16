<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let reviews be written about live hotel bookings and flight
     * bookings, with several photos, as well as local hotel records.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Live hotels and flights have no record in the hotels table.
            $table->unsignedBigInteger('hotel_id')->nullable()->change();

            // One review per booking.
            $table->foreignId('booking_id')->nullable()->unique()->after('hotel_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('flight_booking_id')->nullable()->unique()->after('booking_id')
                ->constrained()->nullOnDelete();

            $table->string('supplier_hotel_id')->nullable()->index()->after('flight_booking_id');
            $table->string('subject_name')->nullable()->after('supplier_hotel_id');
            $table->json('photos')->nullable()->after('photo_path');
        });
    }

    /**
     * Remove the added columns.
     *
     * hotel_id is left optional, because reviews of live hotels and
     * flights could not be kept if it became required again.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
            $table->dropConstrainedForeignId('flight_booking_id');
            $table->dropIndex(['supplier_hotel_id']);
            $table->dropColumn(['supplier_hotel_id', 'subject_name', 'photos']);
        });
    }
};
