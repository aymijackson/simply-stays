<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['host_id']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('host_id')->nullable()->change();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreign('host_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['host_id']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('host_id')->nullable(false)->change();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreign('host_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
