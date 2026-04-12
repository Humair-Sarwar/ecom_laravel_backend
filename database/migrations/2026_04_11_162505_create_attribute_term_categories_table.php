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
        Schema::create('attribute_term_categories', function (Blueprint $table) {
    $table->uuid('attribute_id');
    $table->uuid('category_id');
    $table->uuid('business_id');

    $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
    $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
    $table->foreign('business_id')->references('id')->on('users')->onDelete('cascade');

    $table->primary(['attribute_id', 'category_id', 'business_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_term_categories');
    }
};
