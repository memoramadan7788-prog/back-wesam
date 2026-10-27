<?php

namespace Database\Seeders;

use App\Models\RoleCategory;
use Illuminate\Database\Seeder;

class RoleCategorySeeder extends Seeder
{
    public function run(): void
    {
        RoleCategory::firstOrCreate(
            ['name' => 'user'],
            ['description' => 'Platform-level user roles such as candidate.']
        );

        RoleCategory::firstOrCreate(
            ['name' => 'company'],
            ['description' => 'Roles assigned within a company.']
        );
    }
}
