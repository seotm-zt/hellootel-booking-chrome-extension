<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('nights')->nullable()->after('reservation_at');
        });

        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->string('hotel_name', 500)->nullable()->after('booking_code');
            $table->string('agency_name', 255)->nullable()->after('agency_id');
            $table->unsignedSmallInteger('nights')->nullable()->after('departure_at');
            $table->decimal('commission', 10, 2)->nullable()->after('price');
            $table->string('status', 255)->nullable()->after('commission');
        });
    }

    public function down(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->dropColumn('nights');
        });

        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->dropColumn(['hotel_name', 'agency_name', 'nights', 'commission', 'status']);
        });
    }
};
