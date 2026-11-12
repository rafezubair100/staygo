<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id(); $table->string('supplier')->nullable()->index(); $table->string('supplier_hotel_id')->nullable()->index();
            $table->string('name'); $table->string('slug')->unique(); $table->text('description')->nullable();
            $table->string('address')->nullable(); $table->string('city')->index(); $table->string('country')->index();
            $table->string('country_code', 2)->nullable(); $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable(); $table->unsignedTinyInteger('stars')->nullable();
            $table->decimal('rating', 3, 1)->nullable(); $table->unsignedInteger('review_count')->default(0);
            $table->string('image_url', 1000)->nullable(); $table->json('amenities')->nullable();
            $table->string('source_url', 1000)->nullable(); $table->decimal('indicative_price', 10, 2)->nullable();
            $table->timestamps(); $table->unique(['supplier', 'supplier_hotel_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('hotels'); }
};
