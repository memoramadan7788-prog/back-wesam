<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartBEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_sends_a_six_digit_code_and_verifies_the_user(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'verify@example.com',
        ]);

        $this->postJson('/api/auth/email/resend', ['email' => $user->email])
            ->assertOk();

        $code = null;
        Mail::assertQueued(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code): bool {
            $code = $mail->code;
            return preg_match('/^\d{6}$/', $code) === 1;
        });

        $this->postJson('/api/auth/email/verify', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk()
            ->assertJson(['message' => 'Email verified successfully.']);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_email_resend_is_rate_limited(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/auth/email/resend', ['email' => $user->email])->assertOk();
        $this->postJson('/api/auth/email/resend', ['email' => $user->email])->assertOk();
        $this->postJson('/api/auth/email/resend', ['email' => $user->email])->assertOk();

        $this->postJson('/api/auth/email/resend', ['email' => $user->email])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_user_can_update_locale_and_theme_preferences(): void
    {
        $user = User::factory()->create([
            'locale' => 'ar',
            'theme' => 'system',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/auth/me/preferences', [
                'locale' => 'en',
                'theme' => 'dark',
            ])
            ->assertOk()
            ->assertJsonPath('preferences.locale', 'en')
            ->assertJsonPath('preferences.theme', 'dark');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'en',
            'theme' => 'dark',
        ]);
    }

    public function test_candidate_can_upload_and_delete_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'account_type' => 'candidate',
            'account_status_id' => AccountStatus::ACTIVE,
        ]);

        $upload = $this->actingAs($user)
            ->postJson('/api/candidate/profile/photo', [
                'photo' => UploadedFile::fake()->create('avatar.png', 100, 'image/png'),
            ]);

        $upload->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.photo_path', fn ($path) => is_string($path) && $path !== '');

        $photoPath = $upload->json('data.photo_path');
        Storage::disk('public')->assertExists($photoPath);

        $this->actingAs($user)
            ->deleteJson('/api/candidate/profile/photo')
            ->assertOk();

        Storage::disk('public')->assertMissing($photoPath);
        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $user->id,
            'photo_path' => null,
        ]);
    }

    public function test_company_registration_accepts_extended_contact_data_and_sends_verification(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register/company', [
            'name' => 'Acme Owner',
            'email' => 'owner@acme.example',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Acme Extended Company',
            'industry' => 'Technology',
            'website' => 'https://acme.example',
            'contact_email' => 'contact@acme.example',
            'contact_phone' => '+201000000000',
            'address' => 'Cairo, Egypt',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Extended Company',
            'contact_email' => 'contact@acme.example',
            'contact_phone' => '+201000000000',
            'address' => 'Cairo, Egypt',
        ]);
        Mail::assertQueued(EmailVerificationCodeMail::class);
    }
}
