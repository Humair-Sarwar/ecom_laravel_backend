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
            $table->foreignUuid('image_id')
                  ->nullable()
                  ->after('term_id') // Adjust placement as needed
                  ->constrained('medias') // Points to 'id' on 'medias' table
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_attributes_terms', function (Blueprint $table) {
            $table->foreignUuid('image_id')
                  ->nullable()
                  ->after('term_id') // Adjust placement as needed
                  ->constrained('medias') // Points to 'id' on 'medias' table
                  ->nullOnDelete();
        });
    }
};
