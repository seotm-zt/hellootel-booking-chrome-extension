<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_parser_rules', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 300);
            $table->string('path_match', 300)->default('');
            $table->string('parser', 100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'path_match']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_parser_rules');
    }
};
