<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * URLs stop exposing database ids: records get an unguessable public_id
 * (records with a natural name use a slug instead, e.g. services).
 */
return new class extends Migration
{
    /** Tables whose records appear in URLs and have no slug. */
    private const TABLES = [
        'users', 'bookings', 'event_bookings', 'event_occurrences', 'guest_registrations', 'complimentary_rules',
        'service_orders', 'menu_items', 'menu_item_option_groups', 'menu_item_options', 'menu_item_pairings',
        'reviews', 'support_conversations',
        'ops_inventory_items', 'ops_inventory_locations', 'ops_staff_members', 'ops_shifts',
        'ops_finance_transactions', 'ops_finance_categories',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('public_id', 26)->nullable()->unique()->after('id');
            });

            DB::table($table)->whereNull('public_id')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['public_id' => strtolower((string) Str::ulid())]);
                }
            });
        }

        Schema::table('services', function (Blueprint $t) {
            $t->string('slug')->nullable()->unique()->after('title');
        });

        $taken = [];
        DB::table('services')->orderBy('id')->get(['id', 'title'])->each(function ($service) use (&$taken) {
            $base = Str::slug($service->title) ?: 'service';
            $slug = $base;
            for ($i = 2; isset($taken[$slug]); $i++) {
                $slug = "{$base}-{$i}";
            }
            $taken[$slug] = true;
            DB::table('services')->where('id', $service->id)->update(['slug' => $slug]);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->dropUnique(['slug']);
            $t->dropColumn('slug');
        });

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['public_id']);
                $t->dropColumn('public_id');
            });
        }
    }
};
