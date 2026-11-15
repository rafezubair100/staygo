<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flight bookings are kept apart from hotel bookings, because they
     * hold different information: a route, flights and travellers.
     */
    public function up(): void
    {
        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Supplier references.
            $table->string('supplier')->default('liteapi');
            $table->string('supplier_booking_id')->unique();
            $table->string('booking_ref')->nullable();
            $table->string('pnr')->nullable();
            $table->string('status')->default('pending_confirmation');
            $table->string('payment_status')->nullable();
            $table->boolean('is_sandbox')->default(false);

            // Trip summary.
            $table->string('trip', 20)->default('one_way');
            $table->string('origin', 3);
            $table->string('destination', 3);
            $table->string('origin_name')->nullable();
            $table->string('destination_name')->nullable();
            $table->dateTime('depart_at');
            $table->dateTime('return_at')->nullable();
            $table->string('airlines')->nullable();
            $table->string('cabin')->nullable();
            $table->boolean('refundable')->default(false);

            // Travellers.
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);

            // Price.
            $table->string('currency', 3)->default('GBP');
            $table->decimal('total_amount', 12, 2)->default(0);

            // Full flight details and traveller names, as shown to the guest.
            $table->json('itinerary')->nullable();
            $table->json('passengers')->nullable();

            // Contact for this booking.
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 40)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'depart_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_bookings');
    }
};