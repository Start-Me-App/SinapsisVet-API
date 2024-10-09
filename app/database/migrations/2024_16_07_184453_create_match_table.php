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
        Schema::create('match', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_1_id');
            $table->integer('user_2_id');
            $table->integer('event_id');
            $table->unique(['user_1_id', 'user_2_id']);
            $table->timestamps();

            #aca falta la relacion con el chat
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
