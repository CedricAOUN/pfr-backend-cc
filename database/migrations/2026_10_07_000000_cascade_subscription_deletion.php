<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_items', fn (Blueprint $table) => $table->dropForeign(['subscription_id']));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropForeign(['user_id']));
    }
};
