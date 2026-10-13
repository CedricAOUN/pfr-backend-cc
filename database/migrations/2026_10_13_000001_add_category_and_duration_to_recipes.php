<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            // Legacy recipes have no reliable category or duration to backfill.
            $table->string('category')->nullable()->index();
            $table->unsignedInteger('duration')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'duration']);
        });
    }
};
