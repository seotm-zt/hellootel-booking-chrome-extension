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
        Schema::table('extension_parsers', function (Blueprint $table) {
            $table->unsignedInteger('operator_id')->nullable()->after('is_active');
            $table->string('operator_name', 255)->nullable()->after('operator_id');
        });
    }

    public function down(): void
    {
        Schema::table('extension_parsers', function (Blueprint $table) {
            $table->dropColumn(['operator_id', 'operator_name']);
        });
    }
};
