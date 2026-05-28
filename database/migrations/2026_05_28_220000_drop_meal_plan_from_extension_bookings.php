<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->dropColumn('meal_plan');
        });
    }

    public function down(): void
    {
        Schema::table('extension_bookings', function (Blueprint $table) {
            $table->string('meal_plan')->nullable()->after('infants');
        });
    }
};
