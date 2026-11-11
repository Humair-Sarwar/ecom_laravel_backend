<?php

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
        // 1. Ensure public schema has stock_quantity on products
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'stock_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('stock_quantity')->default(0);
            });
        }

        // 2. Scan all tenant schemas and ensure products has stock_quantity
        $schemas = collect(DB::select("
            SELECT schema_name 
            FROM information_schema.schemata 
            WHERE schema_name NOT IN ('information_schema', 'pg_catalog', 'pg_toast', 'public')
        "))->pluck('schema_name')->toArray();

        foreach ($schemas as $schemaName) {
            $tableExists = DB::selectOne("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = ? AND table_name = 'products'
            ", [$schemaName]);

            if ($tableExists) {
                $hasStockQuantity = DB::selectOne("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = ? AND table_name = 'products' AND column_name = 'stock_quantity'
                ", [$schemaName]);

                if (!$hasStockQuantity) {
                    DB::statement("ALTER TABLE \"{$schemaName}\".\"products\" ADD COLUMN \"stock_quantity\" integer DEFAULT 0");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
