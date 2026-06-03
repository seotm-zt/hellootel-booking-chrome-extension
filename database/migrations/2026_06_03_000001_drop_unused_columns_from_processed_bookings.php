<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These columns are not sent to HelloOtel and not used in any calculations,
     * so they are removed from the schema.
     */
    public function up(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'commission',
                'total_bonus',
                'hm_approval',
                'payment_status_ag',
                'payment_status_rm',
                'payment_status_cm',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('processed_bookings', function (Blueprint $table) {
            $table->decimal('commission', 15, 2)->nullable()->after('price');
            $table->integer('total_bonus')->default(0)->after('currency_code');
            $table->tinyInteger('hm_approval')->nullable()->after('total_bonus');
            $table->tinyInteger('payment_status_ag')->default(0)->after('hm_approval');
            $table->tinyInteger('payment_status_rm')->default(0)->after('payment_status_ag');
            $table->tinyInteger('payment_status_cm')->default(0)->after('payment_status_rm');
        });
    }
};
