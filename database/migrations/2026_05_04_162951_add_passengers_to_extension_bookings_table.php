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
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('adults')->nullable()->after('guests');
            $table->unsignedTinyInteger('children')->nullable()->after('adults');
            $table->unsignedTinyInteger('infants')->nullable()->after('children');
            $table->json('tourists')->nullable()->after('infants');
        });
    }

    public function down(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->dropColumn(['adults', 'children', 'infants', 'tourists']);
        });
    }
};
