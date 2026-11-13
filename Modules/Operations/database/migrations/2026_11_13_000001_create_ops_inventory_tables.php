<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // Set when the location is (part of) a bookable apartment/suite.
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('area')->nullable();
            $table->string('type')->default('other');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'type']);
        });

        Schema::create('ops_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // asset = durable, consumable = used up in operations, supply = daily guest supplies.
            $table->string('kind')->default('asset');
            $table->string('category')->nullable();
            $table->string('unit')->default('pcs');
            $table->unsignedInteger('reorder_level')->nullable();
            $table->unsignedBigInteger('unit_cost_amount')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->string('sku')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['property_id', 'name']);
            $table->index(['property_id', 'kind']);
        });

        Schema::create('ops_inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('ops_inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('ops_inventory_locations')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            // Expected quantity for this location, e.g. 3 towels per suite.
            $table->unsignedInteger('par_level')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'location_id']);
        });

        Schema::create('ops_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('ops_inventory_items')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('from_location_id')->nullable()->constrained('ops_inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('ops_inventory_locations')->nullOnDelete();
            // Positive for every type except adjust, where it is the signed correction.
            $table->integer('quantity');
            $table->unsignedBigInteger('unit_cost_amount')->nullable();
            $table->foreignId('staff_member_id')->nullable()->constrained('ops_staff_members')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('finance_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'occurred_at']);
            $table->index(['item_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_inventory_movements');
        Schema::dropIfExists('ops_inventory_stocks');
        Schema::dropIfExists('ops_inventory_items');
        Schema::dropIfExists('ops_inventory_locations');
    }
};
