<?php

use App\Services\TenantSchemaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
        // Non-destructive: tenant data tables are retained on rollback.
    }
};
