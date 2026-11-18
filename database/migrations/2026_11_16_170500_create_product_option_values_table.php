<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_option_values')) {
            Schema::create('product_option_values', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->index();
                $table->string('option_type', 50)->index(); // series, color, quality, type, storage, ram, processor
                $table->string('title', 300);
                $table->string('slug', 350)->nullable();
                $table->integer('sort_order')->default(1);
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();

                $table->index(['business_id', 'option_type', 'is_deleted'], 'idx_pov_biz_type_del');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
};
