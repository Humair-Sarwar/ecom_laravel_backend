<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 40)->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('users', 'phone_country_code')) {
                $table->string('phone_country_code', 12)->nullable()->default('+44')->after('phone');
            }
            if (!Schema::hasColumn('users', 'alt_phone_country_code')) {
                $table->string('alt_phone_country_code', 12)->nullable()->default('+44')->after('phone_country_code');
            }
            if (!Schema::hasColumn('users', 'alternative_phone')) {
                $table->string('alternative_phone', 30)->nullable()->after('alt_phone_country_code');
            }
            if (!Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('alternative_phone');
            }
            if (!Schema::hasColumn('users', 'auto_submit_eod')) {
                $table->boolean('auto_submit_eod')->default(false)->after('profile_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [
                'gender',
                'phone_country_code',
                'alt_phone_country_code',
                'alternative_phone',
                'profile_image',
                'auto_submit_eod',
            ];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('users', $c));
            if (!empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
