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
        Schema::create('complimentary_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('min_guests')->default(1);
            $table->unsignedInteger('max_quantity_per_guest')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('complimentary_rule_menu_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complimentary_rule_id')->constrained('complimentary_rules')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['complimentary_rule_id', 'menu_item_id'], 'complimentary_rule_menu_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complimentary_rule_menu_item');
        Schema::dropIfExists('complimentary_rules');
    }
};
