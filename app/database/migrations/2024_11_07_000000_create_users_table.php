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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->date('dob')->nullable();
            $table->string('uid')->nullable();
            $table->string('role_id')->nullable();
            $table->string('verification_token')->nullable()->unique();
            $table->integer('active')->default(1);  
            $table->string('password_reset_token')->nullable();
            $table->string('telephone')->nullable();
            $table->string('area_code')->nullable();
            $table->boolean('tyc')->default(0);
            $table->string('nationality_id')->nullable();
            $table->string('sex')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
