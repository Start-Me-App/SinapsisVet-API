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
        Schema::create('installment_detail', function (Blueprint $table) {
            $table->id();
            $table->integer('installment_id');
            $table->integer('installment_number');
            $table->date('due_date');
            $table->string('paid_date')->nullable();
            $table->string('url_payment')->nullable();
            $table->boolean('paid')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installment_detail');
    }
};
