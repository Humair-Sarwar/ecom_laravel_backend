<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'specs')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('specs')->nullable();
            });
        }

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
                $hasSpecs = DB::selectOne("
                    SELECT column_name
                    FROM information_schema.columns
                    WHERE table_schema = ? AND table_name = 'products' AND column_name = 'specs'
                ", [$schemaName]);

                if (!$hasSpecs) {
                    DB::statement("ALTER TABLE \"{$schemaName}\".\"products\" ADD COLUMN \"specs\" json NULL");
                }
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
