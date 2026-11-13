<?php

namespace Modules\Operations\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Operations\Models\FinanceCategory;
use Spatie\Permission\Models\Role;

class OperationsDatabaseSeeder extends Seeder
{
    /** Install-wide defaults: the Staff role and the shared income/expense categories. */
    public function run(): void
    {
        Role::findOrCreate('Staff', 'web');
        FinanceCategory::ensureDefaults();
    }
}
