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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('active_for')->default(1);
            $table->string('condition');
            $table->longText('title');
            $table->boolean('is_web');
            $table->boolean('is_pos');
            $table->boolean('is_hot');
            $table->uuid('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->onUpdate('cascade')->onDelete('set null');
            $table->uuid('brand_id')->nullable();
            $table->foreign('brand_id')->references('id')->on('brands')->onUpdate('cascade')->onDelete('set null');
            $table->integer('sort_order')->default(1);
            $table->text('meta_title')->nullable();
            $table->longText('meta_description')->nullable();
            $table->longText('keywords')->nullable();
            $table->integer('days')->default(1);
            $table->integer('hours')->default(1);
            $table->integer('minutes')->default(1);
            $table->boolean('apply_stock_strictly')->default(true);
            $table->boolean('get_stock_alert')->default(true);
            $table->longText('description')->nullable();

            // 1: simple, 2: variable product
            $table->integer('product_type')->nullable()->default(1);

            $table->string('product_sku')->nullable();
            $table->boolean('product_status_stock')->nullable()->default(true);
            $table->integer('min_stock_alert')->nullable()->default(0);
            $table->decimal('regular_price', 12, 4)->nullable();
            $table->decimal('sale_price', 12, 4)->nullable();
            $table->integer('release_year')->nullable();
            $table->integer('SIM')->nullable();
            $table->integer('network')->nullable();
            $table->integer('carrier_option')->nullable();
            $table->boolean('is_default')->nullable();
            $table->boolean('is_on_sale')->nullable()->default(false);
            $table->boolean('is_published')->default(false);

            $table->uuid('parent_product_id')->nullable();
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
        Schema::dropIfExists('products');
    }
};
