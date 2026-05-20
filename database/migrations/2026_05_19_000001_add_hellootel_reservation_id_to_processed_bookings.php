<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->unsignedInteger('hellootel_reservation_id')->nullable()->after('confirmed_at');
            $table->timestamp('hellootel_sent_at')->nullable()->after('hellootel_reservation_id');
        });
    }

    public function down(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->dropColumn(['hellootel_reservation_id', 'hellootel_sent_at']);
        });
    }
};
