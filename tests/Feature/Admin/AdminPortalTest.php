<?php

namespace Tests\Feature\Admin;

use App\Models\AccountStatus;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'account_type' => 'admin',
            'name'         => 'System Admin',
            'email'        => 'superadmin@example.com',
        ]);
    }

    public function test_admin_can_list_companies_filtered_by_status(): void
    {
        Company::factory()->create(['status' => 'pending', 'name' => 'Pending Corp']);
        Company::factory()->create(['status' => 'approved', 'name' => 'Approved LLC']);
        Company::factory()->create(['status' => 'suspended', 'name' => 'Suspended Inc']);

        // Filter: pending
        $response = $this->actingAs($this->admin)->getJson('/api/admin/companies?status=pending');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'pending');
        $response->assertJsonPath('data.0.name', 'Pending Corp');

        // Filter: approved
        $responseApproved = $this->actingAs($this->admin)->getJson('/api/admin/companies?status=approved');
        $responseApproved->assertStatus(200);
        $responseApproved->assertJsonCount(1, 'data');
        $responseApproved->assertJsonPath('data.0.status', 'approved');
    }

    public function test_admin_can_view_single_company(): void
    {
        $company = Company::factory()->create(['status' => 'pending', 'name' => 'Tech Horizons']);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/companies/{$company->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $company->id);
        $response->assertJsonPath('data.name', 'Tech Horizons');
    }

    public function test_admin_can_approve_company_and_audit_log_is_recorded(): void
    {
        $company = Company::factory()->create([
            'status'      => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/companies/{$company->id}/approve");

        $response->assertStatus(200);
        $response->assertJsonPath('company.status', 'approved');
        $response->assertJsonPath('company.reviewer.id', $this->admin->id);

        $fresh = $company->fresh();
        $this->assertEquals('approved', $fresh->status);
        $this->assertEquals($this->admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'company.approved',
            'user_id'        => $this->admin->id,
            'auditable_type' => Company::class,
            'auditable_id'   => $company->id,
        ]);
    }

    public function test_admin_can_reject_company_with_reason(): void
    {
        $company = Company::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/companies/{$company->id}/reject", [
            'rejection_reason' => 'Commercial register document is illegible.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('company.status', 'rejected');
        $response->assertJsonPath('company.rejection_reason', 'Commercial register document is illegible.');

        $fresh = $company->fresh();
        $this->assertEquals('rejected', $fresh->status);
        $this->assertEquals('Commercial register document is illegible.', $fresh->rejection_reason);
        $this->assertEquals($this->admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'company.rejected',
            'user_id'        => $this->admin->id,
            'auditable_type' => Company::class,
            'auditable_id'   => $company->id,
            'reason'         => 'Commercial register document is illegible.',
        ]);
    }

    public function test_reject_company_requires_reason(): void
    {
        $company = Company::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/companies/{$company->id}/reject", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rejection_reason');
    }

    public function test_admin_can_suspend_company(): void
    {
        $company = Company::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/companies/{$company->id}/suspend", [
            'reason' => 'Suspicious hiring activity reported.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('company.status', 'suspended');

        $fresh = $company->fresh();
        $this->assertEquals('suspended', $fresh->status);
        $this->assertEquals($this->admin->id, $fresh->reviewed_by);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'company.suspended',
            'user_id'        => $this->admin->id,
            'auditable_type' => Company::class,
            'auditable_id'   => $company->id,
        ]);
    }

    public function test_user_without_permission_cannot_review_companies(): void
    {
        $regularUser = User::factory()->create(['account_type' => 'candidate']);
        $company = Company::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($regularUser)->getJson('/api/admin/companies');
        $response->assertStatus(403);

        $responseApprove = $this->actingAs($regularUser)->postJson("/api/admin/companies/{$company->id}/approve");
        $responseApprove->assertStatus(403);
    }

    public function test_non_admin_user_with_platform_permission_cannot_review_companies(): void
    {
        $perm = Permission::create(['key' => 'companies.review', 'description' => 'Review companies']);
        $role = Role::create(['name' => 'company_reviewer', 'scope' => 'platform', 'description' => 'Reviewer']);
        $role->permissions()->attach($perm->id);

        $reviewerUser = User::factory()->create(['account_type' => 'company']);
        $reviewerUser->roles()->attach($role->id, ['assigned_at' => now()]);

        Company::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($reviewerUser)->getJson('/api/admin/companies');
        $response->assertStatus(403);
    }

    public function test_admin_can_list_and_show_users(): void
    {
        User::factory()->count(3)->create(['account_type' => 'candidate']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/users');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'name', 'email', 'account_type', 'roles', 'permissions']]]);

        $firstUser = User::where('account_type', 'candidate')->first();
        $showResponse = $this->actingAs($this->admin)->getJson("/api/admin/users/{$firstUser->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.id', $firstUser->id);
    }

    public function test_admin_can_update_user_account_status(): void
    {
        $user = User::factory()->create(['account_status_id' => AccountStatus::ACTIVE]);

        $response = $this->actingAs($this->admin)->patchJson("/api/admin/users/{$user->id}/status", [
            'account_status_id' => AccountStatus::BLOCKED,
            'reason'            => 'Violated terms of service.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.account_status_id', AccountStatus::BLOCKED);
        $this->assertEquals(AccountStatus::BLOCKED, $user->fresh()->account_status_id);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'user.status_changed',
            'user_id'        => $this->admin->id,
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
        ]);
    }

    public function test_admin_can_assign_and_remove_user_roles(): void
    {
        $company = Company::factory()->create(['status' => 'approved']);
        $role = Role::create(['name' => 'technical_interviewer', 'scope' => 'company', 'description' => 'Interviewer']);
        $user = User::factory()->create();

        // Assign role with company_id
        $assignResponse = $this->actingAs($this->admin)->postJson("/api/admin/users/{$user->id}/roles", [
            'role_id'    => $role->id,
            'company_id' => $company->id,
        ]);

        $assignResponse->assertStatus(200);
        $this->assertTrue($user->fresh()->roles->contains('id', $role->id));

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'user.role_assigned',
            'user_id'        => $this->admin->id,
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
        ]);

        // Remove role
        $removeResponse = $this->actingAs($this->admin)->deleteJson("/api/admin/users/{$user->id}/roles/{$role->id}");

        $removeResponse->assertStatus(200);
        $this->assertFalse($user->fresh()->roles->contains('id', $role->id));

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'user.role_removed',
            'user_id'        => $this->admin->id,
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
        ]);
    }

    public function test_admin_can_view_audit_logs(): void
    {
        Company::factory()->create(['status' => 'pending']);
        $company = Company::first();

        // Perform an action that logs
        $this->actingAs($this->admin)->postJson("/api/admin/companies/{$company->id}/approve");

        $response = $this->actingAs($this->admin)->getJson('/api/admin/audit-logs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'action', 'auditable_type', 'auditable_id', 'user']]]);
        $response->assertJsonPath('data.0.action', 'company.approved');
    }

    public function test_admin_can_view_kpis_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'users' => ['total', 'new_30d', 'prev_30d', 'inactive', 'by_type'],
                'companies' => ['total', 'new_30d', 'by_status'],
                'jobs' => ['total', 'published'],
                'applications' => ['total', 'last_30d', 'prev_30d'],
                'interviews' => ['scheduled'],
                'trend',
                'pending_companies',
                'recent_activity',
                'generated_at',
            ],
        ]);
    }
}