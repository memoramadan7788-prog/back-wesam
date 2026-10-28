<?php

namespace Database\Seeders;

use App\Models\AccountStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Super Admin',
                'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
                'account_status_id' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $role = AdminRole::where('name', 'super_admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([
            $role->id => ['assigned_at' => now()],
        ]);

        $this->command?->warn('Super Admin created/updated in the admins table.');
        $this->command?->warn('Admin login uses email and password.');
    }
}
