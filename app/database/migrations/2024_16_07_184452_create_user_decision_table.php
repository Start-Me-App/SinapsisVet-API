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
        Schema::create('user_decision', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_owner_id');
            $table->integer('user_match_id'); 
            $table->integer('event_id');
            $table->integer('decision');
            $table->unique(['user_owner_id', 'user_match_id','event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_decision');
    }
};
