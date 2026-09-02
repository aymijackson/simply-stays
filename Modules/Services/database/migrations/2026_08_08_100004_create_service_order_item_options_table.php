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
        Schema::create('service_order_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_item_id')->constrained('service_order_items')->cascadeOnDelete();
            $table->foreignId('menu_item_option_id')->nullable()->constrained('menu_item_options')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('price_delta_amount')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('line_total_amount')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_order_item_options');
    }
};
