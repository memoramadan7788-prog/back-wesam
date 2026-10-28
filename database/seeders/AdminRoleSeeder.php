<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = AdminRole::firstOrCreate(
            ['name' => 'super_admin'],
            ['scope' => 'platform', 'description' => 'Platform owner']
        );

        $role->permissions()->sync(
            AdminPermission::whereIn('key', array_keys(AdminPermissionSeeder::ALL))->pluck('id')
        );
    }
}
