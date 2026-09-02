<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CoreDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Guest', 'Host', 'Agent', 'ServiceProvider', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
