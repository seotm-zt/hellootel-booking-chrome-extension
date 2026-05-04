<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('saved_by')->nullable();
            $table->string('booking_code')->nullable()->index();
            $table->string('hotel_name')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('stay_dates')->nullable();
            $table->string('guests')->nullable();
            $table->string('meal_plan')->nullable();
            $table->string('transfer')->nullable();
            $table->string('total_price')->nullable();
            $table->json('statuses')->nullable();
            $table->json('meta')->nullable();
            $table->string('details_link', 1000)->nullable();
            $table->string('thumbnail', 1000)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->string('source_domain', 255)->nullable()->index();
            $table->string('page_title')->nullable();
            $table->string('language', 10)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_bookings');
    }
};
