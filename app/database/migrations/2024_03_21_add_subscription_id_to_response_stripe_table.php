<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('response_stripe', function (Blueprint $table) {
            $table->string('subscription_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('response_stripe', function (Blueprint $table) {
            $table->dropColumn('subscription_id');
        });
    }
}; 