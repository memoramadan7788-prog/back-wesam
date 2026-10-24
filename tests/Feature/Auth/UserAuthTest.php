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
        $response->assertJsonStructure(['message', 'user', 'access_token', 'token', 'token_type']);
        $this->assertSame($response->json('token'), $response->json('access_token'));

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
        $response->assertJsonStructure(['access_token', 'token', 'token_type']);
        $this->assertSame($response->json('token'), $response->json('access_token'));

        $this->assertDatabaseHas('companies', ['name' => 'Acme Technologies', 'status' => 'pending']);
    }

    public function test_employee_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register/employee', [
            'name'                  => 'Alice Employee',
            'email'                 => 'alice@acme.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('user.email', 'alice@acme.com');
        $response->assertJsonPath('user.account_type', 'company');
        $response->assertJsonStructure(['message', 'user', 'token', 'access_token']);

        $this->assertDatabaseHas('users', [
            'email'        => 'alice@acme.com',
            'account_type' => 'company',
        ]);
        $this->assertDatabaseMissing('candidate_profiles', [
            'user_id' => $response->json('user.id'),
        ]);
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
        $response->assertJsonStructure(['message', 'user', 'token', 'access_token', 'token_type']);
        $this->assertSame($response->json('token'), $response->json('access_token'));
    }

    public function test_admin_and_regular_login_use_access_token_as_canonical_key_and_keep_token_alias(): void
    {
        $admin = User::factory()->create([
            'account_type' => 'admin',
            'email'        => 'admin@example.com',
            'password'     => bcrypt('secret123'),
        ]);

        $adminResponse = $this->postJson('/api/admin/login', [
            'email'    => $admin->email,
            'password' => 'secret123',
        ]);

        $adminResponse->assertStatus(200);
        $adminResponse->assertJsonStructure(['access_token', 'token', 'token_type']);
        $this->assertSame($adminResponse->json('token'), $adminResponse->json('access_token'));

        $user = User::factory()->create([
            'account_type' => 'candidate',
            'email'        => 'candidate-login@example.com',
            'password'     => bcrypt('secret123'),
        ]);

        $userResponse = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $userResponse->assertStatus(200);
        $userResponse->assertJsonStructure(['access_token', 'token', 'token_type']);
        $this->assertSame($userResponse->json('token'), $userResponse->json('access_token'));
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

