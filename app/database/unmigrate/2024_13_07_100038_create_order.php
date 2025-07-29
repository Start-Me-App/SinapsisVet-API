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
            $table->string('invoice_name')->nullable();
            $table->string('invoice_email')->nullable();
            $table->string('invoice_address')->nullable();
            $table->string('invoice_document')->nullable();
            $table->float('total_amount_usd')->nullable();
            $table->float('total_amount_ars')->nullable();
            $table->float('discount_percentage')->nullable();
            $table->float('discount_percentage_coupon')->nullable();
            $table->float('discount_amount_usd')->nullable();
            $table->float('discount_amount_ars')->nullable();
            $table->string('coupon_code')->nullable();
            $table->integer('installments')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order');
    }

    public function shouldRun(): bool
    {
        // Return false to skip this migration
        return false;
    }
};
