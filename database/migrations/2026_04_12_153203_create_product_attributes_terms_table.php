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
       Schema::create('product_attributes_terms', function (Blueprint $table) {
    $table->uuid('id')->primary();

    $table->uuid('product_id');
    $table->uuid('attribute_id');
    $table->uuid('term_id');
    $table->uuid('business_id');

    $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
    $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
    $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
    $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();

    $table->timestamps();

    $table->index(['product_id', 'attribute_id', 'term_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attributes_terms');
    }
};
