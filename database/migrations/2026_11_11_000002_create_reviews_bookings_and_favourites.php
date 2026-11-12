<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete(); $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable(); $table->text('body'); $table->string('photo_path')->nullable();
            $table->string('status')->default('pending')->index(); $table->boolean('verified_stay')->default(false);
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete(); $table->string('supplier')->nullable();
            $table->string('supplier_reference')->nullable()->index(); $table->date('check_in'); $table->date('check_out');
            $table->unsignedTinyInteger('adults'); $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('rooms')->default(1); $table->string('currency', 3)->default('GBP');
            $table->decimal('total_amount', 12, 2)->default(0); $table->string('status')->default('pending_supplier_check')->index();
            $table->string('payment_status')->default('not_started'); $table->string('guest_name');
            $table->string('guest_email'); $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('favourites', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete(); $table->timestamps();
            $table->unique(['user_id', 'hotel_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('favourites'); Schema::dropIfExists('bookings'); Schema::dropIfExists('reviews');
    }
};
