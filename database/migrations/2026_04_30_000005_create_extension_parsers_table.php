<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_parsers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('domain', 300)->nullable();
            $table->string('path_match', 500)->nullable();
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_parsers');
    }
};
