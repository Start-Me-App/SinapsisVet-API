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
        Schema::create('response_mercadopago', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('data_id')->nullable();
            $table->integer('order_id')->nullable();
            $table->string('status')->nullable();
            $table->string('preference_id')->nullable();
            $table->string('status_detail')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->integer('payment_method_id')->nullable();
            $table->string('payment_type_id')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('response_mercadopago');
    }
};
