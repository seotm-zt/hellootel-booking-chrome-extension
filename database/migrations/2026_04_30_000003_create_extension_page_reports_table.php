<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_page_reports', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->string('title', 500)->nullable();
            $table->longText('html');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_page_reports');
    }
};
