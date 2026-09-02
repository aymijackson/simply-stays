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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->morphs('holder');
            // Signed, not unsigned: a debit (e.g. reversing a credit on refund) can carry a wallet negative
            // when the holder has already withdrawn funds, which is a real state we need to represent.
            $table->bigInteger('balance')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
            $table->unique(['holder_type', 'holder_id', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
