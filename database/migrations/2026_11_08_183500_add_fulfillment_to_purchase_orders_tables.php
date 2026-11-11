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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('fulfillment_status')->default('fulfilled')->after('status');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('received_quantity')->default(0)->after('quantity');
            $table->text('imei_serial_no')->nullable()->after('description');
            $table->timestamp('received_at')->nullable()->after('imei_serial_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_status');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['received_quantity', 'imei_serial_no', 'received_at']);
        });
    }
};
