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
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_fee_amount')->default(0)->after('status');
            $table->unsignedBigInteger('free_delivery_over_amount')->nullable()->after('delivery_fee_amount');
            $table->json('free_delivery_windows')->nullable()->after('free_delivery_over_amount');
            $table->enum('commission_type', ['none', 'fixed', 'percentage'])->default('none')->after('free_delivery_windows');
            $table->decimal('commission_value', 10, 2)->nullable()->after('commission_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee_amount', 'free_delivery_over_amount', 'free_delivery_windows', 'commission_type', 'commission_value']);
        });
    }
};
