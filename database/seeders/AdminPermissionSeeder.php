<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use Illuminate\Database\Seeder;

class AdminPermissionSeeder extends Seeder
{
    public const ALL = [
        'companies.review' => 'Approve or reject company registrations',
        'companies.suspend' => 'Suspend a company',
        'roles.manage' => 'Manage admin and platform roles',
        'permissions.manage' => 'Manage the admin permission list',
        'users.manage' => 'Manage all platform users',
        'audit.view' => 'View audit logs',
        'reports.view' => 'View platform reports',
        'chatbot.admin' => 'Use the admin chatbot',
    ];

    public function run(): void
    {
        foreach (self::ALL as $key => $description) {
            AdminPermission::firstOrCreate(['key' => $key], ['description' => $description]);
        }
    }
}
