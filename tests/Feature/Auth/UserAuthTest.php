<?php

namespace Tests\Feature\Auth;

use App\Models\AccountStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'candidate'], ['scope' => 'platform', 'description' => 'Candidate']);
        Role::firstOrCreate(['name' => 'company_admin'], ['scope' => 'company', 'description' => 'Company Admin']);
    }

    public function test_candidate_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register/candidate', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone'                 => '+201001234567',
            'headline'              => 'Senior PHP Developer',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('user.email', 'john@example.com');
        $response->assertJsonPath('user.account_type', 'candidate');
        $response->assertJsonStructure(['message', 'user', 'token']);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('candidate_profiles', ['headline' => 'Senior PHP Developer']);
    }

    public function test_company_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register/company', [
            'name'                  => 'Acme HR',
            'email'                 => 'hr@acme.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'company_name'          => 'Acme Technologies',
            'industry'              => 'Software',
            'website'               => 'https://acme.com',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('user.email', 'hr@acme.com');
        $response->assertJsonPath('company.name', 'Acme Technologies');

        $this->assertDatabaseHas('companies', ['name' => 'Acme Technologies', 'status' => 'pending']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email'             => 'user@example.com',
            'password'          => bcrypt('secret123'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email'             => 'blocked@example.com',
            'password'          => bcrypt('secret123'),
            'account_status_id' => AccountStatus::BLOCKED,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'blocked@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_fetch_me_and_logout(): void
    {
        $user = User::factory()->create();

        $meResponse = $this->actingAs($user)->getJson('/api/auth/me');
        $meResponse->assertStatus(200);
        $meResponse->assertJsonPath('data.id', $user->id);

        $logoutResponse = $this->actingAs($user)->postJson('/api/auth/logout');
        $logoutResponse->assertStatus(200);
    }
}

