<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-service charges. Null = use the property's rate (other charge has no property default).
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('service_charge_percent', 5, 2)->nullable()->after('commission_value');
            $table->decimal('tax_percent', 5, 2)->nullable()->after('service_charge_percent');
            $table->string('other_charge_label', 60)->nullable()->after('tax_percent');
            $table->decimal('other_charge_percent', 5, 2)->nullable()->after('other_charge_label');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            // Orders placed from a QR code may come from people without an account.
            $table->foreignId('customer_id')->nullable()->change();

            $table->string('contact_name')->nullable()->after('customer_id');
            $table->string('contact_phone', 40)->nullable()->after('contact_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->foreignId('location_id')->nullable()->after('booking_id')->constrained('ops_inventory_locations')->nullOnDelete();
            $table->string('location_label')->nullable()->after('location_id');
            $table->string('source', 20)->default('web')->after('location_label');
            $table->string('payment_method', 20)->nullable()->after('source');
            $table->string('public_token', 64)->nullable()->unique()->after('payment_method');
            $table->timestamp('seen_at')->nullable()->after('public_token');
        });

        Schema::create('service_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // Null = generic code for the service; set = that room is pre-selected.
            $table->foreignId('location_id')->nullable()->constrained('ops_inventory_locations')->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('scans')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_qr_codes');

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropUnique(['public_token']);
            $table->dropColumn(['contact_name', 'contact_phone', 'contact_email', 'location_label', 'source', 'payment_method', 'public_token', 'seen_at']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['service_charge_percent', 'tax_percent', 'other_charge_label', 'other_charge_percent']);
        });
    }
};
