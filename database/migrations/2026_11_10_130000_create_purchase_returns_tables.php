<?php

use App\Services\TenantSchemaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('purchase_returns')) {
            Schema::create('purchase_returns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->nullable()->index();
                $table->uuid('supplier_id');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onUpdate('cascade')->onDelete('cascade');
                $table->uuid('purchase_order_id')->nullable();
                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
                $table->string('purchase_order_no')->nullable()->index();
                $table->string('return_no')->unique()->index();
                $table->date('return_date');
                $table->integer('total_items')->default(0);
                $table->decimal('total_amount', 15, 2)->default(0.00);
                $table->decimal('return_amount', 15, 2)->default(0.00);
                $table->decimal('remaining_amount', 15, 2)->default(0.00);
                $table->decimal('cash_paid', 15, 2)->default(0.00);
                $table->decimal('card_paid', 15, 2)->default(0.00);
                $table->decimal('bank_paid', 15, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->uuid('created_by')->nullable();
                $table->string('created_by_name')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('purchase_return_items')) {
            Schema::create('purchase_return_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('purchase_return_id');
                $table->foreign('purchase_return_id')->references('id')->on('purchase_returns')->onUpdate('cascade')->onDelete('cascade');
                $table->uuid('purchase_order_item_id')->nullable()->index();
                $table->uuid('product_id')->nullable();
                $table->string('product_name');
                $table->string('sku')->nullable();
                $table->string('barcode')->nullable();
                $table->decimal('unit_cost', 15, 2)->default(0.00);
                $table->integer('quantity')->default(1);
                $table->decimal('total_cost', 15, 2)->default(0.00);
                $table->text('imei_serial_no')->nullable();
                $table->timestamps();
            });
        }

        // Provision into all existing tenant schemas
        $schemas = collect(DB::select("
            SELECT schema_name
            FROM information_schema.schemata
            WHERE schema_name NOT IN ('information_schema', 'pg_catalog', 'pg_toast', 'public')
              AND schema_name NOT LIKE 'pg_%'
        "))->pluck('schema_name')->toArray();

        foreach ($schemas as $schemaName) {
            TenantSchemaService::provisionTenantSchema($schemaName);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
