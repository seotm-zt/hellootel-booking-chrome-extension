<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('saved_by_user_id')->nullable()->after('source_booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->dropColumn('saved_by_user_id');
        });
    }
};
