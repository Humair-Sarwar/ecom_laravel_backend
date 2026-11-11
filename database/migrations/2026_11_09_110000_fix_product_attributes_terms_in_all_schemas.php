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
        // 1. Ensure public schema has sort_order and image_id
        if (Schema::hasTable('product_attributes_terms')) {
            if (!Schema::hasColumn('product_attributes_terms', 'sort_order')) {
                Schema::table('product_attributes_terms', function (Blueprint $table) {
                    $table->integer('sort_order')->default(0);
                });
            }
            if (!Schema::hasColumn('product_attributes_terms', 'image_id')) {
                Schema::table('product_attributes_terms', function (Blueprint $table) {
                    $table->uuid('image_id')->nullable();
                });
            }
        }

        // 2. Scan all tenant schemas and ensure product_attributes_terms has sort_order and image_id
        $schemas = collect(DB::select("
            SELECT schema_name 
            FROM information_schema.schemata 
            WHERE schema_name NOT IN ('information_schema', 'pg_catalog', 'pg_toast', 'public')
        "))->pluck('schema_name')->toArray();

        foreach ($schemas as $schemaName) {
            $tableExists = DB::selectOne("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = ? AND table_name = 'product_attributes_terms'
            ", [$schemaName]);

            if ($tableExists) {
                // Check sort_order
                $hasSortOrder = DB::selectOne("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = ? AND table_name = 'product_attributes_terms' AND column_name = 'sort_order'
                ", [$schemaName]);

                if (!$hasSortOrder) {
                    DB::statement("ALTER TABLE \"{$schemaName}\".\"product_attributes_terms\" ADD COLUMN \"sort_order\" integer DEFAULT 0");
                }

                // Check image_id
                $hasImageId = DB::selectOne("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = ? AND table_name = 'product_attributes_terms' AND column_name = 'image_id'
                ", [$schemaName]);

                if (!$hasImageId) {
                    DB::statement("ALTER TABLE \"{$schemaName}\".\"product_attributes_terms\" ADD COLUMN \"image_id\" uuid NULL");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op or drop columns
    }
};
