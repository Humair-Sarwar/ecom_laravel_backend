<?php

use App\Services\TenantSchemaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_orders')) {
            Schema::create('pos_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('business_id')->index();
                $table->string('order_no', 64)->index();
                $table->string('status', 30)->default('cart')->index();
                $table->string('transaction_type', 30)->default('sale')->index();
                $table->uuid('customer_id')->nullable()->index();
                $table->string('customer_name', 255)->nullable();
                $table->string('customer_phone', 50)->nullable();
                $table->uuid('order_type_id')->nullable();
                $table->string('order_type_name', 150)->nullable();
                $table->uuid('order_process_id')->nullable();
                $table->string('order_process_name', 150)->nullable();
                $table->decimal('subtotal', 15, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->decimal('tax_amount', 15, 2)->default(0.00);
                $table->decimal('total_amount', 15, 2)->default(0.00);
                $table->decimal('paid_amount', 15, 2)->default(0.00);
                $table->decimal('balance_amount', 15, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->uuid('created_by')->nullable()->index();
                $table->string('created_by_name', 150)->nullable();
                $table->uuid('served_by')->nullable();
                $table->string('served_by_name', 150)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->boolean('is_deleted')->default(false)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pos_order_items')) {
            Schema::create('pos_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('pos_order_id')->index();
                $table->foreign('pos_order_id')->references('id')->on('pos_orders')->onUpdate('cascade')->onDelete('cascade');
                $table->uuid('product_id')->nullable()->index();
                $table->uuid('variation_id')->nullable();
                $table->string('transaction_type', 30)->default('sale')->index();
                $table->string('product_name', 500);
                $table->string('sku', 150)->nullable();
                $table->string('vat_status', 100)->nullable()->default('VAT Exempt');
                $table->integer('qty')->default(1);
                $table->decimal('unit_price', 15, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->string('discount_type', 20)->default('fixed');
                $table->decimal('line_total', 15, 2)->default(0.00);
                $table->text('imei_serial_no')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

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

    public function down(): void
    {
        // Non-destructive: tenant data tables are retained on rollback.
    }
};
