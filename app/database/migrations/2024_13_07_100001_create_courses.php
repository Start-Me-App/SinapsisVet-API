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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->integer('profesor_id');
            $table->string('title');
            $table->float('price_ars');
            $table->float('price_usd');
            $table->string('description');
            $table->string('presentation');
            $table->string('objective');
            $table->integer('active');
            $table->integer('category_id');
            $table->string('photo_url');
            $table->date('starting_date');
            $table->date('inscription_date');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
