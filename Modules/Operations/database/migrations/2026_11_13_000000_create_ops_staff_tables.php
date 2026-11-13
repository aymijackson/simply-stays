<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_staff_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('position');
            $table->string('department')->nullable();
            // basic = own shifts only, inventory = + store keeping, manager = everything for the property.
            $table->string('access_level')->default('basic');
            $table->string('employment_type')->default('full_time');
            $table->date('hired_on')->nullable();
            $table->unsignedBigInteger('salary_amount')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->string('emergency_contact')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['property_id', 'user_id']);
            $table->index(['property_id', 'status']);
        });

        Schema::create('ops_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained('ops_staff_members')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('area')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('scheduled');
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->text('handover_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_id', 'starts_at']);
            $table->index(['staff_member_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_shifts');
        Schema::dropIfExists('ops_staff_members');
    }
};
