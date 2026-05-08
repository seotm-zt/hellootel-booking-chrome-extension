<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable()->after('status');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->dropColumn(['confirmed_by_user_id', 'confirmed_at']);
        });
    }
};
