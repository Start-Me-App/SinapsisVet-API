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
        Schema::create('hotmart_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->index();
            $table->string('transaction_id', 255)->nullable()->index();
            $table->string('product_id', 255)->nullable()->index();
            $table->string('product_name', 500)->nullable();
            $table->string('buyer_email', 255)->nullable()->index();
            $table->string('buyer_name', 255)->nullable();
            $table->string('status', 50)->nullable();
            $table->decimal('price_value', 10, 2)->nullable();
            $table->string('price_currency', 10)->nullable();
            $table->decimal('commission_value', 10, 2)->nullable();
            $table->timestamp('approved_date')->nullable();
            $table->json('raw_data')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->boolean('processed')->default(false)->index();
            $table->timestamps();

            // Foreign key constraint (opcional)
            $table->foreign('order_id')->references('id')->on('order')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotmart_events');
    }
};
