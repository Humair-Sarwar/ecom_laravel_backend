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
        Schema::create('business_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_id')->nullable()->index(); // references user who owns group
            $table->string('status')->default('active'); // active, inactive
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_groups');
    }
};
