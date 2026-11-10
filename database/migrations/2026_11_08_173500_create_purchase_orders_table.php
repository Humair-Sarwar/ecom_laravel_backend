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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');

            $table->uuid('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onUpdate('cascade')->onDelete('cascade');

            $table->string('order_no')->unique()->index();
            $table->date('order_date');
            $table->string('status')->default('completed'); // draft, completed, cancelled
            $table->boolean('is_trade_in')->default(false);

            $table->decimal('grand_total', 15, 2)->default(0.00);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->decimal('balance', 15, 2)->default(0.00);

            $table->decimal('cash_paid', 15, 2)->default(0.00);
            $table->decimal('card_paid', 15, 2)->default(0.00);
            $table->decimal('bank_paid', 15, 2)->default(0.00);
            $table->boolean('use_today_sales')->default(false);

            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('created_by_name')->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onUpdate('cascade')->onDelete('cascade');

            $table->uuid('product_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('condition')->nullable();
            $table->string('category_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('warranty')->nullable();
            $table->string('year')->nullable();

            $table->decimal('unit_cost', 15, 2)->default(0.00);
            $table->decimal('pack_price', 15, 2)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('total_cost', 15, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->string('item_status')->default('ready_for_sell');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
