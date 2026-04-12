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
        Schema::create('product_images', function (Blueprint $table) {
        $table->uuid('product_id');
        $table->uuid('media_id');
        $table->uuid('business_id');

        $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        $table->foreign('media_id')->references('id')->on('medias')->cascadeOnDelete();
        $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();

        $table->timestamps();

        // optional but recommended
        $table->primary(['product_id', 'media_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
