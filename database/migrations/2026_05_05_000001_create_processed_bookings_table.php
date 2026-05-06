<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_booking_id')->nullable()->unique()->index();
            $table->string('booking_code')->nullable()->index();
            $table->json('tourists')->nullable();
            $table->string('hotel_code')->nullable();
            $table->string('room_type_code')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->date('arrival_at')->nullable();
            $table->date('departure_at')->nullable();
            $table->string('agency_code')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->unsignedTinyInteger('adults')->default(0);
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->timestamps();

            $table->foreign('source_booking_id')
                ->references('id')->on('extension_bookings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_bookings');
    }
};
