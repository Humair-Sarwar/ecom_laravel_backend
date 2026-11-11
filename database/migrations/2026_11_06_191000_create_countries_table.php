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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nice_name')->nullable();
            $table->string('iso3', 5)->nullable();
            $table->string('iso', 5)->nullable();
            $table->string('phone_code', 10)->nullable();
            $table->string('num_code', 10)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('time_zone')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
