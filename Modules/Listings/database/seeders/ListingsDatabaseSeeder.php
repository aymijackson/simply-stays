<?php

namespace Modules\Listings\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Listings\Models\Amenity;

class ListingsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'WiFi',
            'Air conditioning',
            'Kitchen',
            'Free parking',
            'Washer',
            'TV',
            'Pool',
            'Breakfast included',
            'Workspace',
            'Pet friendly',
        ] as $name) {
            Amenity::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
