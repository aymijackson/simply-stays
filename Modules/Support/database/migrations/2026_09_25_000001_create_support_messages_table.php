<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->string('sender_type');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('read_by_admin_at')->nullable();
            $table->timestamp('read_by_customer_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
