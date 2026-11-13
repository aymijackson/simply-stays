<?php

return [
    'name' => 'Operations',

    // Currency used for inventory costs, salaries and the property books.
    'currency' => env('OPERATIONS_CURRENCY', 'NGN'),

    // Property the notebook inventory snapshot is imported into when running
    // `php artisan db:seed --class="Modules\Operations\Database\Seeders\InventorySnapshotSeeder"`.
    // Accepts an id or a property name; falls back to the first property.
    'snapshot_property' => env('OPERATIONS_SNAPSHOT_PROPERTY'),
];
