<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('business_expense_types')) {
            Schema::create('business_expense_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('business_id')->index();
                $table->string('expense_category', 40)->default('daily')->index(); // 'daily' | 'business'
                $table->string('name', 255);
                $table->integer('sort_order')->default(1);
                $table->boolean('is_default')->default(false);
                $table->uuid('created_by')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
        }

        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'smtp_active')) {
                $table->boolean('smtp_active')->default(false)->after('daily_stock_movement_report');
            }
            if (!Schema::hasColumn('businesses', 'smtp_port')) {
                $table->string('smtp_port', 20)->nullable()->after('smtp_active');
            }
            if (!Schema::hasColumn('businesses', 'smtp_host')) {
                $table->string('smtp_host', 255)->nullable()->after('smtp_port');
            }
            if (!Schema::hasColumn('businesses', 'smtp_email')) {
                $table->string('smtp_email', 255)->nullable()->after('smtp_host');
            }
            if (!Schema::hasColumn('businesses', 'smtp_password')) {
                $table->text('smtp_password')->nullable()->after('smtp_email');
            }
            if (!Schema::hasColumn('businesses', 'stock_alert_after_sale')) {
                $table->boolean('stock_alert_after_sale')->default(false)->after('smtp_password');
            }
            if (!Schema::hasColumn('businesses', 'stock_alert_eod_list')) {
                $table->boolean('stock_alert_eod_list')->default(false)->after('stock_alert_after_sale');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expense_types');

        Schema::table('businesses', function (Blueprint $table) {
            $cols = [
                'smtp_active',
                'smtp_port',
                'smtp_host',
                'smtp_email',
                'smtp_password',
                'stock_alert_after_sale',
                'stock_alert_eod_list',
            ];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('businesses', $c));
            if (!empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
