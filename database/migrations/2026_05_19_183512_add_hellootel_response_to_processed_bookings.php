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
            $table->text('hellootel_response')->nullable()->after('hellootel_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->dropColumn('hellootel_response');
        });
    }
};
