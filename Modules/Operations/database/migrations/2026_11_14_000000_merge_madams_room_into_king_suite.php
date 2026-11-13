<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The notebook's "Madam's room" is the King Suite. The first import created it
 * as its own location; fold its stock and history into King Suite — Living room.
 * Does nothing on databases that never had the separate location.
 */
return new class extends Migration
{
    public function up(): void
    {
        $madamRooms = DB::table('ops_inventory_locations')->where('name', "Madam's room")->get();

        foreach ($madamRooms as $madam) {
            DB::transaction(function () use ($madam) {
                $target = DB::table('ops_inventory_locations')
                    ->where('property_id', $madam->property_id)
                    ->where('name', 'King Suite')
                    ->orderByRaw("CASE WHEN area = 'Living room' THEN 0 ELSE 1 END")
                    ->orderBy('id')
                    ->first();

                if (! $target) {
                    // No King Suite location at all: turn this one into it.
                    DB::table('ops_inventory_locations')->where('id', $madam->id)->update([
                        'name' => 'King Suite',
                        'area' => 'Living room',
                        'type' => 'apartment',
                        'updated_at' => now(),
                    ]);

                    return;
                }

                foreach (DB::table('ops_inventory_stocks')->where('location_id', $madam->id)->get() as $stock) {
                    $existing = DB::table('ops_inventory_stocks')
                        ->where('location_id', $target->id)
                        ->where('item_id', $stock->item_id)
                        ->first();

                    if ($existing) {
                        DB::table('ops_inventory_stocks')->where('id', $existing->id)->update([
                            'quantity' => $existing->quantity + $stock->quantity,
                            'updated_at' => now(),
                        ]);
                        DB::table('ops_inventory_stocks')->where('id', $stock->id)->delete();
                    } else {
                        DB::table('ops_inventory_stocks')->where('id', $stock->id)->update([
                            'location_id' => $target->id,
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('ops_inventory_movements')->where('from_location_id', $madam->id)->update(['from_location_id' => $target->id]);
                DB::table('ops_inventory_movements')->where('to_location_id', $madam->id)->update(['to_location_id' => $target->id]);
                DB::table('ops_inventory_movements')
                    ->where('property_id', $madam->property_id)
                    ->where('note', "Umbrellas carried to Madam's room, 2 left")
                    ->update(['note' => "Umbrellas carried to Madam's room (King Suite), 2 left"]);

                DB::table('ops_inventory_locations')->where('id', $madam->id)->delete();
            });
        }
    }

    public function down(): void
    {
        // Not reversible: the merged stock can't be told apart again.
    }
};
