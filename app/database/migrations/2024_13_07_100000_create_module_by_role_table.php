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
        Schema::create('module_by_role', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('module_id');
            $table->integer('role_id');
            $table->integer('list');
            $table->integer('create');
            $table->integer('update');
            $table->integer('delete');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_by_role');
    }
};
