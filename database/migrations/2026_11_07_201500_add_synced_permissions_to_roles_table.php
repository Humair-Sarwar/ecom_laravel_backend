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
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'synced_permissions')) {
                $table->json('synced_permissions')->nullable()->after('permissions');
            }
        });

        // Initialize synced_permissions with existing permissions so current active roles keep working
        DB::table('roles')
            ->whereNull('synced_permissions')
            ->update([
                'synced_permissions' => DB::raw('permissions')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'synced_permissions')) {
                $table->dropColumn('synced_permissions');
            }
        });
    }
};
