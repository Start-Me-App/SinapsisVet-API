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
        Schema::create('notification_mercadopago', function (Blueprint $table) {
            $table->id();
            $table->integer('id_webhook');
            $table->string('live_mode');
            $table->string('date_created');
            $table->string('user_id_mercadopago');
            $table->string('api_version');
            $table->string('action');
            $table->string('data_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_mercadopago');
    }
};
