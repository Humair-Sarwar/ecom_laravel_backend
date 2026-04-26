<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_attributes_terms', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_attributes_terms', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('business_id');
        });
    }
};
