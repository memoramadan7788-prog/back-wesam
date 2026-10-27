<?php

namespace Database\Seeders;

use App\Models\AccountStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Services\GoogleAuthenticatorService;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $totp = app(GoogleAuthenticatorService::class);
        $configuredSecret = env('ADMIN_2FA_SECRET');
        $secret = $configuredSecret ?: $totp->generateSecret();

        $admin = Admin::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Super Admin',
                'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
                'account_status_id' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
            ]
        );

        if ($admin->two_factor_secret) {
            $secret = $admin->two_factor_secret;
        } else {
            $admin->update([
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
            ]);
        }

        $role = AdminRole::where('name', 'super_admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([
            $role->id => ['assigned_at' => now()],
        ]);

        $this->command?->warn('Super Admin created/updated in the admins table.');
        $this->command?->warn('Google Authenticator setup key: ' . $secret);
        $this->command?->warn('Google Authenticator URI: ' . $totp->provisioningUri(
            $secret,
            $admin->email,
            env('ADMIN_2FA_ISSUER', 'HR Management System')
        ));
        $this->command?->warn('Add the setup key to Google Authenticator before login.');
    }
}
