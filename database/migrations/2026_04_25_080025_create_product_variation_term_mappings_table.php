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
        Schema::create('product_variation_term_mappings', function (Blueprint $table) {
            // Aapki baki tables ki tarah yahan uuid use karein
            $table->uuid('id')->primary();

            // Link to the generated variation (child product)
            $table->uuid('product_id');
            // Link to the specific term (e.g., 128GB, Red)
            $table->uuid('term_id');

            // Foreign keys as per your schema
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');

            $table->timestamps();

            // Indexing for faster searching
            $table->index(['product_id', 'term_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variation_term_mappings');
    }
};