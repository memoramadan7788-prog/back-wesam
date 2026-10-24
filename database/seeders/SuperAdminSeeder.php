<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed super admin user in users table
        $user = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Super Admin',
                'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
                'account_type' => 'admin',
                'account_status_id' => \App\Models\AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $role = Role::where('name', 'super_admin')->firstOrFail();
        $user->roles()->syncWithoutDetaching([
            $role->id => ['company_id' => null, 'assigned_at' => now()],
        ]);

        // Admin authentication uses the users table with account_type=admin.
        // There is intentionally no separate admins-table record.
    }
}
