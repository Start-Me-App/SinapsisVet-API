<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('response_dlocal', function (Blueprint $table) {
            $table->decimal('fee_usd', 10, 4)->nullable()->after('currency');
            $table->decimal('net_amount_usd', 10, 4)->nullable()->after('fee_usd');
            $table->decimal('net_amount_ars', 12, 2)->nullable()->after('net_amount_usd');
            $table->decimal('exchange_rate', 12, 2)->nullable()->after('net_amount_ars');
        });
    }

    public function down(): void
    {
        Schema::table('response_dlocal', function (Blueprint $table) {
            $table->dropColumn(['fee_usd', 'net_amount_usd', 'net_amount_ars', 'exchange_rate']);
        });
    }
};
