<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('processed_booking_id')->nullable()->after('id');
            $table->foreign('processed_booking_id')
                ->references('id')->on('processed_bookings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->dropForeign(['processed_booking_id']);
            $table->dropColumn('processed_booking_id');
        });
    }
};
