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
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->integer('course_id');
            $table->string('name');
            $table->string('video_url');
            $table->string('description');
            $table->integer('active');
            $table->date('date')->nullable();
            $table->time('time')->nullable();

            $table->bigInteger('zoom_meeting_id')->nullable();
            $table->string('zoom_passcode')->nullable();    

            #$table->foreign('course_id')->references('id')->on('courses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshops');
    }
};
