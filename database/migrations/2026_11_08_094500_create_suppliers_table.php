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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');

            $table->string('supplier_name');
            $table->string('full_name'); // Contact person
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
