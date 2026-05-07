<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->string('reservation_at', 50)->nullable()->after('stay_dates');
        });
    }

    public function down(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->dropColumn('reservation_at');
        });
    }
};
