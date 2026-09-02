<?php

namespace Modules\Services\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Services\Models\ServiceCategory;

class ServicesDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry', 'description' => 'Pickup and drop-off laundry service for guests.'],
            ['name' => 'Logistics', 'slug' => 'logistics', 'fulfillment_type' => 'logistics', 'description' => 'Airport transfers and local transport.'],
            ['name' => 'Food & Dining', 'slug' => 'food', 'fulfillment_type' => 'food', 'description' => 'In-house restaurants and food vendors with delivery.'],
            ['name' => 'Concierge', 'slug' => 'concierge', 'fulfillment_type' => 'other', 'description' => 'Other auxiliary services arranged on request.'],
        ] as $category) {
            ServiceCategory::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
