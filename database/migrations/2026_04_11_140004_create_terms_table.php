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
        Schema::create('terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('term_slug');
            $table->integer('sort_order')->default(1);
            $table->uuid('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('medias')->onUpdate('cascade')->onDelete('set null');
            $table->longText('description')->nullable();
            $table->uuid('attribute_id');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onUpdate('cascade')->onDelete('cascade');
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
