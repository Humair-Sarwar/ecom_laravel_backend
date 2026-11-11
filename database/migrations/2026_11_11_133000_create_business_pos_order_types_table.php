<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('business_pos_order_types')) {
            Schema::create('business_pos_order_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('business_id')->index();
                $table->uuid('parent_id')->nullable()->index();
                $table->string('name', 255);
                $table->integer('sort_order')->default(1);
                $table->boolean('is_default')->default(false);
                $table->uuid('created_by')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_pos_order_types');
    }
};
