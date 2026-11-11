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
        if (!Schema::hasTable('daily_expenses')) {
            Schema::create('daily_expenses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('business_id')->index();
                $table->uuid('expense_type_id')->nullable()->index();
                $table->string('expense_type_name', 255);
                $table->date('expense_date')->index();
                $table->decimal('amount', 15, 2)->default(0.00);
                $table->string('transaction_type', 30)->default('cash')->index();
                $table->text('description')->nullable();
                $table->boolean('in_store_payment')->default(false);
                $table->uuid('created_by')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->boolean('is_deleted')->default(false)->index();
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

    public function down(): void
    {
        Schema::dropIfExists('daily_expenses');
    }
};
