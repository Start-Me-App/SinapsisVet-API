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
        Schema::create('response_dlocal', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('order_id')->nullable();
            $table->string('status')->nullable();
            $table->string('payment_id')->nullable();
            $table->string('redirect_url', 1024)->nullable();
            $table->string('subscription_id')->nullable();
            $table->string('currency', 8)->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('response_dlocal');
    }
};
