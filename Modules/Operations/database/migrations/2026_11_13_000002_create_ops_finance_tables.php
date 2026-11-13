<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_finance_categories', function (Blueprint $table) {
            $table->id();
            // Null = a default category shared by every property.
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('slug')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'type']);
        });

        Schema::create('ops_finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('ops_finance_categories')->restrictOnDelete();
            $table->string('type');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('NGN');
            $table->date('occurred_on');
            $table->string('payment_method')->default('cash');
            $table->string('counterparty')->nullable();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained('ops_staff_members')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_id', 'occurred_on']);
            $table->index(['property_id', 'type', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_finance_transactions');
        Schema::dropIfExists('ops_finance_categories');
    }
};
