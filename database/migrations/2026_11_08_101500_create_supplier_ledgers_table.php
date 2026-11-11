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
        Schema::create('supplier_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');

            $table->uuid('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onUpdate('cascade')->onDelete('cascade');

            $table->string('reference_no')->index();
            $table->string('transaction_type'); // purchase_order, payment, previous_balance
            $table->decimal('purchase_amount', 15, 2)->default(0.00);
            $table->decimal('payment_amount', 15, 2)->default(0.00);
            $table->decimal('balance', 15, 2)->default(0.00); // Running balance

            $table->string('payment_type')->default('N/A'); // Cash, Bank Transfer, Card, etc.
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->boolean('use_pos_balance')->default(false);

            $table->uuid('created_by')->nullable();
            $table->string('created_by_name')->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ledgers');
    }
};
