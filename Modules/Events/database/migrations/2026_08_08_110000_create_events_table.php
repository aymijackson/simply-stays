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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('location_address')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_ticketed')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedBigInteger('price_amount')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('commission_type', ['none', 'fixed', 'percentage'])->default('none');
            $table->decimal('commission_value', 10, 2)->nullable();
            $table->enum('recurrence_type', ['none', 'daily', 'weekly', 'monthly'])->default('none');
            $table->json('recurrence_days_of_week')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'published', 'rejected', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
