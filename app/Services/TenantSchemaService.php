<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class TenantSchemaService
{
    /**
     * Sanitize a schema name to ensure safe PostgreSQL identifier.
     */
    public static function sanitizeSchemaName(string $name): string
    {
        $clean = strtolower(trim($name));
        $clean = preg_replace('/[^a-z0-9_]/', '_', $clean);
        $clean = preg_replace('/_+/', '_', $clean);
        return trim($clean, '_') ?: 'public';
    }

    /**
     * Check if a schema exists in PostgreSQL.
     */
    public static function schemaExists(string $schemaName): bool
    {
        $result = DB::selectOne(
            "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
            [$schemaName]
        );
        return $result !== null;
    }

    /**
     * Get all active non-system schemas from PostgreSQL.
     */
    public static function getAllSchemas(): array
    {
        return collect(DB::select("
            SELECT schema_name 
            FROM information_schema.schemata 
            WHERE schema_name NOT IN ('information_schema', 'pg_catalog', 'pg_toast')
            ORDER BY schema_name ASC
        "))->pluck('schema_name')->values()->toArray();
    }

    /**
     * Provision and migrate a new or existing tenant schema.
     */
    public static function provisionTenantSchema(string $rawSchemaName, ?string $businessId = null): array
    {
        $schemaName = self::sanitizeSchemaName($rawSchemaName);

        if ($schemaName === 'public') {
            return [
                'success' => true,
                'schema_name' => 'public',
                'tables' => [],
                'message' => 'Using default public schema.',
            ];
        }

        // 1. Create Schema in PostgreSQL
        DB::statement("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");

        // 2. Configure dynamic connection with tenant search_path
        $baseConfig = Config::get('database.connections.pgsql');
        $baseConfig['search_path'] = "\"{$schemaName}\",public";
        Config::set('database.connections.tenant_pgsql', $baseConfig);
        DB::purge('tenant_pgsql');

        $tenantSchema = Schema::connection('tenant_pgsql');
        $tablesCreated = [];

        // 3. Provision 'brands' table
        if (!$tenantSchema->hasTable('brands')) {
            $tenantSchema->create('brands', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('slug');
                $table->integer('sort_order')->default(1);
                $table->uuid('image_id')->nullable();
                $table->boolean('status')->default(true);
                $table->uuid('business_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'brands';
        }

        // 4. Provision 'categories' table
        if (!$tenantSchema->hasTable('categories')) {
            $tenantSchema->create('categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('category_slug');
                $table->integer('sort_order')->default(1);
                $table->uuid('image_id')->nullable();
                $table->uuid('cover_image_id')->nullable();
                $table->string('meta_title')->nullable();
                $table->longText('meta_description')->nullable();
                $table->longText('page_description')->nullable();
                $table->integer('level')->default(0);
                $table->uuid('parent_category_id')->nullable();
                $table->uuid('business_id')->nullable();
                $table->integer('is_active_for_buy')->default(1);
                $table->boolean('is_listing_switch_in_buy')->default(false);
                $table->integer('listing_design')->default(1);
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'categories';
        } else {
            // Ensure is_active_for_buy exists if created previously with 'purpose'
            if (!$tenantSchema->hasColumn('categories', 'is_active_for_buy')) {
                if ($tenantSchema->hasColumn('categories', 'purpose')) {
                    $tenantSchema->table('categories', function (Blueprint $table) {
                        $table->renameColumn('purpose', 'is_active_for_buy');
                    });
                } else {
                    $tenantSchema->table('categories', function (Blueprint $table) {
                        $table->integer('is_active_for_buy')->default(1);
                    });
                }
            }
        }

        // 5. Provision 'attributes' table
        if (!$tenantSchema->hasTable('attributes')) {
            $tenantSchema->create('attributes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('attribute_slug');
                $table->longText('sub_title')->nullable();
                $table->uuid('image_id')->nullable();
                $table->longText('description')->nullable();
                $table->integer('design')->default(1);
                $table->integer('is_active_for_buy')->default(1);
                $table->uuid('business_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'attributes';
        }

        // 6. Provision 'terms' table
        if (!$tenantSchema->hasTable('terms')) {
            $tenantSchema->create('terms', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('term_slug');
                $table->integer('sort_order')->default(1);
                $table->uuid('image_id')->nullable();
                $table->longText('description')->nullable();
                $table->uuid('attribute_id');
                $table->uuid('business_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'terms';
        }

        // 7. Provision 'attribute_term_categories' table
        if (!$tenantSchema->hasTable('attribute_term_categories')) {
            $tenantSchema->create('attribute_term_categories', function (Blueprint $table) {
                $table->uuid('attribute_id');
                $table->uuid('category_id');
                $table->uuid('business_id')->nullable();
                $table->timestamps();
                $table->primary(['attribute_id', 'category_id']);
            });
            $tablesCreated[] = 'attribute_term_categories';
        }

        // 8. Provision 'products' table
        if (!$tenantSchema->hasTable('products')) {
            $tenantSchema->create('products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->integer('active_for')->default(1);
                $table->string('condition')->default('new');
                $table->longText('title');
                $table->boolean('is_web')->default(true);
                $table->boolean('is_pos')->default(true);
                $table->boolean('is_hot')->default(false);
                $table->uuid('category_id')->nullable();
                $table->uuid('brand_id')->nullable();
                $table->integer('sort_order')->default(1);
                $table->text('meta_title')->nullable();
                $table->longText('meta_description')->nullable();
                $table->longText('keywords')->nullable();
                $table->integer('days')->default(1);
                $table->integer('hours')->default(1);
                $table->integer('minutes')->default(1);
                $table->boolean('apply_stock_strictly')->default(true);
                $table->boolean('get_stock_alert')->default(true);
                $table->longText('description')->nullable();
                $table->integer('product_type')->nullable()->default(1);
                $table->uuid('image_id')->nullable();
                $table->string('product_slug')->unique()->nullable();
                $table->string('product_sku')->nullable();
                $table->boolean('product_status_stock')->nullable()->default(true);
                $table->integer('min_stock_alert')->nullable()->default(0);
                $table->double('regular_price', 15, 2)->nullable();
                $table->double('sale_price', 15, 2)->nullable();
                $table->integer('release_year')->nullable();
                $table->integer('SIM')->nullable();
                $table->integer('network')->nullable();
                $table->integer('carrier_option')->nullable();
                $table->boolean('is_default')->nullable();
                $table->boolean('is_on_sale')->nullable()->default(false);
                $table->boolean('is_published')->default(true);
                $table->uuid('parent_product_id')->nullable();
                $table->uuid('business_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'products';
        }

        // 9. Provision 'product_images' table
        if (!$tenantSchema->hasTable('product_images')) {
            $tenantSchema->create('product_images', function (Blueprint $table) {
                $table->uuid('product_id');
                $table->uuid('media_id');
                $table->uuid('business_id')->nullable();
                $table->timestamps();
                $table->primary(['product_id', 'media_id']);
            });
            $tablesCreated[] = 'product_images';
        }

        // 10. Provision 'product_attributes_terms' table
        if (!$tenantSchema->hasTable('product_attributes_terms')) {
            $tenantSchema->create('product_attributes_terms', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('product_id');
                $table->uuid('attribute_id');
                $table->uuid('term_id');
                $table->uuid('business_id')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'attribute_id', 'term_id']);
            });
            $tablesCreated[] = 'product_attributes_terms';
        }

        // 11. Provision 'product_variation_term_mappings' table
        if (!$tenantSchema->hasTable('product_variation_term_mappings')) {
            $tenantSchema->create('product_variation_term_mappings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('product_id');
                $table->uuid('term_id');
                $table->timestamps();
                $table->index(['product_id', 'term_id']);
            });
            $tablesCreated[] = 'product_variation_term_mappings';
        }

        // 12. Provision 'policies' table
        if (!$tenantSchema->hasTable('policies')) {
            $tenantSchema->create('policies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->longText('description')->nullable();
                $table->integer('type')->default(1);
                $table->boolean('approved')->default(true);
                $table->uuid('business_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'policies';
        }

        // 13. Provision 'site_menus' table
        if (!$tenantSchema->hasTable('site_menus')) {
            $tenantSchema->create('site_menus', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->json('menu')->nullable();
                $table->string("key")->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('menu_title')->nullable();
                $table->uuid('business_id')->nullable();
                $table->timestamps();
            });
            $tablesCreated[] = 'site_menus';
        }

        // 14. Provision 'store_settings' table
        if (!$tenantSchema->hasTable('store_settings')) {
            $tenantSchema->create('store_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
            $tablesCreated[] = 'store_settings';
        }

        return [
            'success' => true,
            'schema_name' => $schemaName,
            'tables_created' => $tablesCreated,
            'message' => "PostgreSQL schema [{$schemaName}] successfully configured with " . count($tablesCreated) . " tables.",
        ];
    }
}
