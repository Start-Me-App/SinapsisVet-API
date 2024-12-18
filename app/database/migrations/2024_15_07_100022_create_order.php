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
        Schema::create('order', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('status');
            $table->datetime('date_created');
            $table->datetime('date_last_updated');
            $table->datetime('date_closed')->nullable();
            $table->datetime('date_paid')->nullable();
            $table->integer('shopping_cart_id')->unique();
            $table->string('payment_method_id');
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
