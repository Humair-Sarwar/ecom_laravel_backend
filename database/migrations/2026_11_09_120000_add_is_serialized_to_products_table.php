<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add to public schema
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'is_serialized')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_serialized')->default(false);
            });
        }

        // 2. Add to all tenant schemas
        $businesses = DB::table('businesses')->whereNotNull('schema_name')->get();
        foreach ($businesses as $biz) {
            $schemaName = preg_replace('/[^a-zA-Z0-9_]/', '', $biz->schema_name);
            if (!empty($schemaName)) {
                DB::statement("ALTER TABLE \"{$schemaName}\".products ADD COLUMN IF NOT EXISTS is_serialized BOOLEAN DEFAULT FALSE;");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'is_serialized')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_serialized');
            });
        }

        $businesses = DB::table('businesses')->whereNotNull('schema_name')->get();
        foreach ($businesses as $biz) {
            $schemaName = preg_replace('/[^a-zA-Z0-9_]/', '', $biz->schema_name);
            if (!empty($schemaName)) {
                DB::statement("ALTER TABLE \"{$schemaName}\".products DROP COLUMN IF EXISTS is_serialized;");
            }
        }
    }
};
