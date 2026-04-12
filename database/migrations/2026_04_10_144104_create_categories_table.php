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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('category_slug');
            $table->integer('sort_order');
            $table->uuid('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('medias')->onUpdate('cascade')->onDelete('set null');
            $table->uuid('cover_image_id')->nullable();
            $table->foreign('cover_image_id')->references('id')->on('medias')->onUpdate('cascade')->onDelete('set null');
            $table->string('meta_title')->nullable();
            $table->longText('meta_description')->nullable();
            $table->longText('page_description')->nullable();
            $table->integer('level')->default(0);
            $table->uuid('parent_category_id')->nullable();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->integer('purpose')->default(1);
            $table->boolean('is_listing_switch_in_buy')->default(false);
            $table->integer('listing_design')->default(1);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
