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
            $table->string('title');
            $table->float('price_ars', 16, 2);
            $table->float('price_usd', 16, 2);
            $table->longText('description');
            $table->longText('presentation');
            $table->longText('objective');
            $table->integer('active');
            $table->integer('category_id');
            $table->string('photo_url');
            $table->date('starting_date');
            $table->date('inscription_date');
            $table->string('asociation_path')->nullable();
            $table->string('subtitle')->nullable();
            $table->longText('destined_to')->nullable();
            $table->longText('certifications')->nullable();
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
