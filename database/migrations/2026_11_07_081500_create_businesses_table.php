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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('owner_id')->nullable()->index(); // UUID string referencing users
            $table->string('role_id')->nullable()->index();  // UUID string referencing roles
            $table->string('role_title')->nullable();
            $table->string('name');
            $table->string('branch_name')->nullable();
            $table->string('business_type')->nullable(); // e.g. Electronics Store, Liquor Store, Apparel Store
            $table->string('email')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('whatsapp_no')->nullable();

            // Website options
            $table->boolean('is_system_website')->default(false);
            $table->boolean('is_personal_website')->default(false);
            $table->string('website_url')->nullable();

            // Package & Services
            $table->string('package_level')->default('Basic'); // Basic, Standard, Premium
            $table->json('services')->nullable(); // ['sale', 'trade_in', 'repair', 'pos', 'on_store', 'own_site']

            // Schema & Multi-tenancy
            $table->string('schema_type')->default('new'); // 'new' or 'existing'
            $table->string('schema_name')->nullable()->index(); // e.g. 'qaisar_malik', 'public'

            // Address & Location
            $table->text('address')->nullable();
            $table->string('city_name')->nullable();
            $table->string('state_region')->nullable();
            $table->string('post_code')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('country_name')->nullable();
            $table->string('currency')->default('GBP'); // e.g. GBP, PKR, USD

            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->text('description')->nullable();

            // Status & lifecycle
            $table->string('status')->default('active'); // 'active', 'blocked'

            $table->timestamps();
            $table->softDeletes(); // For 'deleted' tab
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
