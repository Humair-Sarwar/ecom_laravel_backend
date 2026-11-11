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
        Schema::create('lab_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            
            $table->string('name');
            $table->string('barcode')->nullable()->index();
            $table->string('condition')->default('Brand New'); // Brand New, Refurbished, Used, etc.
            $table->string('quality')->default('Premium'); // Premium, Original OEM, Aftermarket, A Grade
            
            $table->uuid('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            
            $table->uuid('brand_id')->nullable();
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            
            $table->string('unit_of_measure')->default('Piece'); // Piece, Box, Pack, Set, etc.
            $table->integer('min_stock_alert')->default(0);
            $table->integer('sort_order')->default(1);
            
            $table->integer('stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->decimal('last_cost', 12, 2)->default(0.00);
            $table->decimal('selling_price', 12, 2)->default(0.00);
            
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('lab_item_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            
            $table->uuid('lab_item_id');
            $table->foreign('lab_item_id')->references('id')->on('lab_items')->onUpdate('cascade')->onDelete('cascade');
            
            $table->string('order_id')->nullable(); // e.g. 198-1126-11
            $table->string('order_from')->default('POS'); // POS, Repair, Sales, Direct
            $table->integer('quantity')->default(1);
            $table->decimal('pack_price', 12, 2)->default(0.00);
            $table->decimal('total_price', 12, 2)->default(0.00);
            $table->string('created_by_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_item_usages');
        Schema::dropIfExists('lab_items');
    }
};
