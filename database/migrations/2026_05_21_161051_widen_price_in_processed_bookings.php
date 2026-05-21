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
            $table->decimal('price', 15, 2)->nullable()->change();
            $table->decimal('commission', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->change();
            $table->decimal('commission', 10, 2)->nullable()->change();
        });
    }
};
