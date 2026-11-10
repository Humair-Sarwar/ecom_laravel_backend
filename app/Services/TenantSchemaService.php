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

        // 2. Configure dynamic connection strictly scoped to the tenant schema
        $baseConfig = Config::get('database.connections.pgsql');
        $baseConfig['search_path'] = "\"{$schemaName}\"";
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
                $table->integer('stock_quantity')->default(0);
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
                $table->boolean('is_serialized')->default(false);
                $table->uuid('parent_product_id')->nullable();
                $table->uuid('business_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'products';
        } else {
            if (!$tenantSchema->hasColumn('products', 'stock_quantity')) {
                $tenantSchema->table('products', function (Blueprint $table) {
                    $table->integer('stock_quantity')->default(0);
                });
            }
            if (!$tenantSchema->hasColumn('products', 'is_serialized')) {
                $tenantSchema->table('products', function (Blueprint $table) {
                    $table->boolean('is_serialized')->default(false);
                });
            }
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
                $table->uuid('image_id')->nullable();
                $table->integer('sort_order')->default(0);
                $table->uuid('business_id')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'attribute_id', 'term_id']);
            });
            $tablesCreated[] = 'product_attributes_terms';
        } else {
            if (!$tenantSchema->hasColumn('product_attributes_terms', 'sort_order')) {
                $tenantSchema->table('product_attributes_terms', function (Blueprint $table) {
                    $table->integer('sort_order')->default(0);
                });
            }
            if (!$tenantSchema->hasColumn('product_attributes_terms', 'image_id')) {
                $tenantSchema->table('product_attributes_terms', function (Blueprint $table) {
                    $table->uuid('image_id')->nullable();
                });
            }
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

        // 15. Provision 'medias' table
        if (!$tenantSchema->hasTable('medias')) {
            $tenantSchema->create('medias', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('media_path');
                $table->uuid('business_id')->nullable()->index();
                $table->timestamps();
            });
            $tablesCreated[] = 'medias';
        }

        // 16. Provision 'lab_items' table
        if (!$tenantSchema->hasTable('lab_items')) {
            $tenantSchema->create('lab_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->nullable()->index();
                $table->string('name');
                $table->string('barcode')->nullable()->index();
                $table->string('condition')->default('Brand New');
                $table->string('quality')->default('Premium');
                $table->uuid('category_id')->nullable();
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
                $table->uuid('brand_id')->nullable();
                $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
                $table->string('unit_of_measure')->default('Piece');
                $table->integer('min_stock_alert')->default(0);
                $table->integer('sort_order')->default(1);
                $table->integer('stock')->default(0);
                $table->integer('reserved_stock')->default(0);
                $table->decimal('last_cost', 12, 2)->default(0.00);
                $table->decimal('selling_price', 12, 2)->default(0.00);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'lab_items';
        }

        // 17. Provision 'lab_item_usages' table
        if (!$tenantSchema->hasTable('lab_item_usages')) {
            $tenantSchema->create('lab_item_usages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->nullable()->index();
                $table->uuid('lab_item_id');
                $table->foreign('lab_item_id')->references('id')->on('lab_items')->onUpdate('cascade')->onDelete('cascade');
                $table->string('order_id')->nullable();
                $table->string('order_from')->default('POS');
                $table->integer('quantity')->default(1);
                $table->decimal('pack_price', 12, 2)->default(0.00);
                $table->decimal('total_price', 12, 2)->default(0.00);
                $table->string('created_by_name')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
            $tablesCreated[] = 'lab_item_usages';
        }

        // 18. Provision 'suppliers' table
        if (!$tenantSchema->hasTable('suppliers')) {
            $tenantSchema->create('suppliers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->nullable()->index();
                $table->string('supplier_name');
                $table->string('full_name');
                $table->string('country_code')->default('+44');
                $table->string('mobile_no')->nullable();
                $table->string('email')->nullable();
                $table->text('description')->nullable();
                $table->string('tax_definition')->default('N/A');
                $table->boolean('is_default')->default(false);
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->uuid('created_by')->nullable();
                $table->string('created_by_name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'suppliers';
        }

        // 19. Provision 'supplier_ledgers' table
        if (!$tenantSchema->hasTable('supplier_ledgers')) {
            $tenantSchema->create('supplier_ledgers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->nullable()->index();
                $table->uuid('supplier_id');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onUpdate('cascade')->onDelete('cascade');
                $table->string('reference_no')->index();
                $table->string('transaction_type');
                $table->decimal('purchase_amount', 15, 2)->default(0.00);
                $table->decimal('payment_amount', 15, 2)->default(0.00);
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->string('payment_type')->default('N/A');
                $table->date('transaction_date');
                $table->text('description')->nullable();
                $table->boolean('use_pos_balance')->default(false);
                $table->uuid('created_by')->nullable();
                $table->string('created_by_name')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'supplier_ledgers';
        }

        // 20. Provision 'purchase_orders' table
        if (!$tenantSchema->hasTable('purchase_orders')) {
            $tenantSchema->create('purchase_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->nullable()->index();
                $table->uuid('supplier_id');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onUpdate('cascade')->onDelete('cascade');
                $table->string('order_no')->unique()->index();
                $table->date('order_date');
                $table->string('status')->default('completed');
                $table->string('fulfillment_status')->default('fulfilled');
                $table->boolean('is_trade_in')->default(false);
                $table->decimal('grand_total', 15, 2)->default(0.00);
                $table->decimal('paid_amount', 15, 2)->default(0.00);
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->decimal('cash_paid', 15, 2)->default(0.00);
                $table->decimal('card_paid', 15, 2)->default(0.00);
                $table->decimal('bank_paid', 15, 2)->default(0.00);
                $table->boolean('use_today_sales')->default(false);
                $table->text('notes')->nullable();
                $table->uuid('created_by')->nullable();
                $table->string('created_by_name')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
            $tablesCreated[] = 'purchase_orders';
        } else {
            if (!$tenantSchema->hasColumn('purchase_orders', 'fulfillment_status')) {
                $tenantSchema->table('purchase_orders', function (Blueprint $table) {
                    $table->string('fulfillment_status')->default('fulfilled');
                });
            }
        }

        // 21. Provision 'purchase_order_items' table
        if (!$tenantSchema->hasTable('purchase_order_items')) {
            $tenantSchema->create('purchase_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('purchase_order_id');
                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onUpdate('cascade')->onDelete('cascade');
                $table->uuid('product_id')->nullable();
                $table->string('product_name');
                $table->string('sku')->nullable();
                $table->string('barcode')->nullable();
                $table->string('condition')->nullable();
                $table->string('category_name')->nullable();
                $table->string('brand_name')->nullable();
                $table->string('warranty')->nullable();
                $table->string('year')->nullable();
                $table->decimal('unit_cost', 15, 2)->default(0.00);
                $table->decimal('pack_price', 15, 2)->nullable();
                $table->integer('quantity')->default(1);
                $table->integer('received_quantity')->default(0);
                $table->decimal('total_cost', 15, 2)->default(0.00);
                $table->text('description')->nullable();
                $table->text('imei_serial_no')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->string('item_status')->default('ready_for_sell');
                $table->timestamps();
            });
            $tablesCreated[] = 'purchase_order_items';
        } else {
            if (!$tenantSchema->hasColumn('purchase_order_items', 'received_quantity')) {
                $tenantSchema->table('purchase_order_items', function (Blueprint $table) {
                    $table->integer('received_quantity')->default(0);
                    $table->text('imei_serial_no')->nullable();
                    $table->timestamp('received_at')->nullable();
                });
            }
        }

        // 22. Provision 'purchase_returns' table
        if (!$tenantSchema->hasTable('purchase_returns')) {
            $tenantSchema->create('purchase_returns', function (Blueprint $table) {
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
            $tablesCreated[] = 'purchase_returns';
        }

        // 23. Provision 'purchase_return_items' table
        if (!$tenantSchema->hasTable('purchase_return_items')) {
            $tenantSchema->create('purchase_return_items', function (Blueprint $table) {
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
            $tablesCreated[] = 'purchase_return_items';
        }

        return [
            'success' => true,
            'schema_name' => $schemaName,
            'tables_created' => $tablesCreated,
            'message' => "PostgreSQL schema [{$schemaName}] successfully configured with " . count($tablesCreated) . " tables.",
        ];
    }
}
