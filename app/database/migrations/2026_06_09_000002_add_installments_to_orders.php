<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order', 'installments')) {
            return;
        }
        Schema::table('order', function (Blueprint $table) {
            $table->integer('installments')->default(1)->after('discount_percentage_coupon');
        });
    }

    public function down(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn('installments');
        });
    }
};
