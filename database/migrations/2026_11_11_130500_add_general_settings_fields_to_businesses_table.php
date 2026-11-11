<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'mobile_country_code')) {
                $table->string('mobile_country_code', 12)->nullable()->default('+44')->after('mobile_no');
            }
            if (!Schema::hasColumn('businesses', 'whatsapp_country_code')) {
                $table->string('whatsapp_country_code', 12)->nullable()->default('+44')->after('whatsapp_no');
            }
            if (!Schema::hasColumn('businesses', 'logo_image')) {
                $table->string('logo_image')->nullable()->after('website_url');
            }
            if (!Schema::hasColumn('businesses', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('logo_image');
            }
            if (!Schema::hasColumn('businesses', 'delivery_days')) {
                $table->integer('delivery_days')->default(3)->after('longitude');
            }
            if (!Schema::hasColumn('businesses', 'delivery_hours')) {
                $table->integer('delivery_hours')->default(0)->after('delivery_days');
            }
            if (!Schema::hasColumn('businesses', 'delivery_minutes')) {
                $table->integer('delivery_minutes')->default(0)->after('delivery_hours');
            }
            if (!Schema::hasColumn('businesses', 'max_shipping_amount')) {
                $table->decimal('max_shipping_amount', 12, 2)->default(0)->after('delivery_minutes');
            }
            if (!Schema::hasColumn('businesses', 'shipping_charges')) {
                $table->decimal('shipping_charges', 12, 2)->default(0)->after('max_shipping_amount');
            }
            if (!Schema::hasColumn('businesses', 'tax_title')) {
                $table->string('tax_title', 100)->nullable()->default('VAT')->after('currency');
            }
            if (!Schema::hasColumn('businesses', 'tax_value')) {
                $table->decimal('tax_value', 8, 2)->default(0)->after('tax_title');
            }
            if (!Schema::hasColumn('businesses', 'vat_registration_no')) {
                $table->string('vat_registration_no', 100)->nullable()->after('tax_value');
            }
            if (!Schema::hasColumn('businesses', 'enable_vat_tax')) {
                $table->boolean('enable_vat_tax')->default(false)->after('vat_registration_no');
            }
            if (!Schema::hasColumn('businesses', 'disallow_pos_duplicate_cart')) {
                $table->boolean('disallow_pos_duplicate_cart')->default(false)->after('enable_vat_tax');
            }
            if (!Schema::hasColumn('businesses', 'daily_stock_movement_report')) {
                $table->boolean('daily_stock_movement_report')->default(false)->after('disallow_pos_duplicate_cart');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $cols = [
                'mobile_country_code',
                'whatsapp_country_code',
                'logo_image',
                'cover_image',
                'delivery_days',
                'delivery_hours',
                'delivery_minutes',
                'max_shipping_amount',
                'shipping_charges',
                'tax_title',
                'tax_value',
                'vat_registration_no',
                'enable_vat_tax',
                'disallow_pos_duplicate_cart',
                'daily_stock_movement_report',
            ];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('businesses', $c));
            if (!empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
