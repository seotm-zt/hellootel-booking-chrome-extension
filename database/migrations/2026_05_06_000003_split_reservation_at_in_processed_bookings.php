<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->date('reservation_date')->nullable()->after('departure_at');
            $table->string('reservation_time', 5)->nullable()->after('reservation_date')->comment('HH:MM');
        });

        // Migrate existing data from reservation_at → reservation_date + reservation_time
        DB::statement("
            UPDATE processed_bookings
            SET
                reservation_date = DATE(reservation_at),
                reservation_time = TIME_FORMAT(reservation_at, '%H:%i')
            WHERE reservation_at IS NOT NULL
        ");

        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->dropColumn('reservation_at');
        });
    }

    public function down(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->timestamp('reservation_at')->nullable()->after('departure_at');
        });

        DB::statement("
            UPDATE processed_bookings
            SET reservation_at = CONCAT(reservation_date, ' ', COALESCE(reservation_time, '00:00'), ':00')
            WHERE reservation_date IS NOT NULL
        ");

        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->dropColumn(['reservation_date', 'reservation_time']);
        });
    }
};
