<?php

namespace Tests\Feature\Company;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyEmployeeOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $companyOwner;
    protected User $hrUser;
    protected User $regularEmployee;
    protected Role $companyAdminRole;
    protected Role $hrRole;
    protected Role $interviewerRole;
    protected Role $regularRole;
    protected Role $platformAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->companyAdminRole = Role::where('name', 'company_admin')->firstOrFail();
        $this->hrRole = Role::where('name', 'hr')->firstOrFail();
        $this->interviewerRole = Role::where('name', 'interviewer')->firstOrFail();
        $this->regularRole = Role::where('name', 'employee')->firstOrFail();
        $this->platformAdminRole = Role::where('name', 'super_admin')->firstOrFail();

        // Company A
        $this->companyOwner = User::factory()->create([
            'account_type' => 'company',
            'name'         => 'Owner User',
            'email'        => 'owner@companya.com',
        ]);

        $this->companyA = Company::factory()->create([
            'owner_user_id' => $this->companyOwner->id,
            'status'        => 'approved',
            'name'          => 'Company A',
        ]);

        CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $this->companyOwner->id,
            'requested_job_title' => 'CEO & Founder',
            'status'              => 'approved',
            'role_id'             => $this->companyAdminRole->id,
            'joined_at'           => now(),
        ]);

        $this->companyOwner->roles()->attach($this->companyAdminRole->id, [
            'company_id'  => $this->companyA->id,
            'assigned_at' => now(),
        ]);

        // HR user in Company A (also has employees.approve and employees.manage for testing)
        $this->hrUser = User::factory()->create([
            'account_type' => 'company',
            'name'         => 'HR Manager',
            'email'        => 'hr@companya.com',
        ]);

        CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $this->hrUser->id,
            'requested_job_title' => 'HR Specialist',
            'status'              => 'approved',
            'role_id'             => $this->companyAdminRole->id,
            'joined_at'           => now(),
        ]);

        $this->hrUser->roles()->attach($this->companyAdminRole->id, [
            'company_id'  => $this->companyA->id,
            'assigned_at' => now(),
        ]);

        // Regular employee in Company A (no management permissions)
        $this->regularEmployee = User::factory()->create([
            'account_type' => 'company',
            'name'         => 'Regular Worker',
            'email'        => 'worker@companya.com',
        ]);

        CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $this->regularEmployee->id,
            'requested_job_title' => 'Junior Developer',
            'status'              => 'approved',
            'role_id'             => $this->regularRole->id,
            'joined_at'           => now(),
        ]);

        $this->regularEmployee->roles()->attach($this->regularRole->id, [
            'company_id'  => $this->companyA->id,
            'assigned_at' => now(),
        ]);

        // Company B (for multi-tenant isolation tests)
        $ownerB = User::factory()->create(['account_type' => 'company']);
        $this->companyB = Company::factory()->create([
            'owner_user_id' => $ownerB->id,
            'status'        => 'approved',
            'name'          => 'Company B',
        ]);
    }

    public function test_user_can_submit_join_request(): void
    {
        $applicant = User::factory()->create(['account_type' => 'candidate', 'name' => 'John Applicant']);

        $response = $this->actingAs($applicant)->postJson('/api/company/join-requests', [
            'company_id'           => $this->companyA->id,
            'requested_job_title'  => 'Senior Technical Interviewer',
            'requested_department' => 'Engineering',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('employee.company_id', $this->companyA->id);
        $response->assertJsonPath('employee.requested_job_title', 'Senior Technical Interviewer');
        $response->assertJsonPath('employee.requested_department', 'Engineering');
        $response->assertJsonPath('employee.status', 'pending');

        $this->assertDatabaseHas('company_employees', [
            'company_id'          => $this->companyA->id,
            'user_id'             => $applicant->id,
            'requested_job_title' => 'Senior Technical Interviewer',
            'status'              => 'pending',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'employee.join_requested',
            'user_id' => $applicant->id,
        ]);
    }

    public function test_user_cannot_submit_duplicate_join_request_returns_409(): void
    {
        $applicant = User::factory()->create(['account_type' => 'candidate']);

        // First request succeeds
        $this->actingAs($applicant)->postJson('/api/company/join-requests', [
            'company_id'          => $this->companyA->id,
            'requested_job_title' => 'QA Engineer',
        ])->assertStatus(201);

        // Second request returns 409 Conflict
        $response = $this->actingAs($applicant)->postJson('/api/company/join-requests', [
            'company_id'          => $this->companyA->id,
            'requested_job_title' => 'DevOps Engineer',
        ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('already submitted a join request', $response->json('message'));
    }

    public function test_user_cannot_join_non_approved_company_returns_403(): void
    {
        $pendingCompany = Company::factory()->create(['status' => 'pending']);
        $applicant = User::factory()->create(['account_type' => 'candidate']);

        $response = $this->actingAs($applicant)->postJson('/api/company/join-requests', [
            'company_id'          => $pendingCompany->id,
            'requested_job_title' => 'Analyst',
        ]);

        $response->assertStatus(403);
    }

    public function test_company_hr_can_list_employees_with_status_filter(): void
    {
        // Create a pending applicant
        $applicant = User::factory()->create(['account_type' => 'candidate']);
        CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $applicant->id,
            'requested_job_title' => 'Tech Lead',
            'status'              => 'pending',
            'requested_at'        => now(),
        ]);

        // Filter: pending
        $respPending = $this->actingAs($this->hrUser)->getJson('/api/company/employees?status=pending');
        $respPending->assertStatus(200);
        $respPending->assertJsonCount(1, 'data');
        $respPending->assertJsonPath('data.0.status', 'pending');
        $respPending->assertJsonPath('data.0.requested_job_title', 'Tech Lead');

        // Filter: approved
        $respApproved = $this->actingAs($this->hrUser)->getJson('/api/company/employees?status=approved');
        $respApproved->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($respApproved->json('data')));
    }

    public function test_company_can_approve_employee_with_company_role_and_notification_created(): void
    {
        $applicant = User::factory()->create(['account_type' => 'candidate', 'name' => 'Alice Interviewer']);
        $employeeRecord = CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $applicant->id,
            'requested_job_title' => 'Lead Interviewer',
            'status'              => 'pending',
            'requested_at'        => now(),
        ]);

        $response = $this->actingAs($this->hrUser)->postJson("/api/company/employees/{$employeeRecord->id}/approve", [
            'role_id' => $this->interviewerRole->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('employee.status', 'approved');
        $response->assertJsonPath('employee.role.name', 'interviewer');
        $response->assertJsonPath('employee.reviewer.id', $this->hrUser->id);

        // Check user_roles table
        $this->assertDatabaseHas('user_roles', [
            'user_id'     => $applicant->id,
            'role_id'     => $this->interviewerRole->id,
            'company_id'  => $this->companyA->id,
            'assigned_by' => $this->hrUser->id,
        ]);

        // Check notification created
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $applicant->id,
            'type'         => 'company.join_approved',
        ]);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'employee.approved',
            'user_id' => $this->hrUser->id,
        ]);
    }

    public function test_approve_rejects_platform_scoped_role_with_422(): void
    {
        $applicant = User::factory()->create(['account_type' => 'candidate']);
        $employeeRecord = CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $applicant->id,
            'requested_job_title' => 'Manager',
            'status'              => 'pending',
            'requested_at'        => now(),
        ]);

        // Attempt to assign 'super_admin' role which has scope = 'platform'
        $response = $this->actingAs($this->hrUser)->postJson("/api/company/employees/{$employeeRecord->id}/approve", [
            'role_id' => $this->platformAdminRole->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role_id']);
    }

    public function test_company_can_reject_employee_with_reason(): void
    {
        $applicant = User::factory()->create(['account_type' => 'candidate']);
        $employeeRecord = CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $applicant->id,
            'requested_job_title' => 'Specialist',
            'status'              => 'pending',
            'requested_at'        => now(),
        ]);

        $response = $this->actingAs($this->hrUser)->postJson("/api/company/employees/{$employeeRecord->id}/reject", [
            'rejection_reason' => 'Qualifications do not match current openings.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('employee.status', 'rejected');
        $response->assertJsonPath('employee.rejection_reason', 'Qualifications do not match current openings.');
        $response->assertJsonPath('employee.reviewer.id', $this->hrUser->id);

        $this->assertDatabaseHas('company_employees', [
            'id'               => $employeeRecord->id,
            'status'           => 'rejected',
            'rejection_reason' => 'Qualifications do not match current openings.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'employee.rejected',
            'user_id' => $this->hrUser->id,
        ]);
    }

    public function test_cannot_approve_or_reject_non_pending_employee_returns_409(): void
    {
        $applicant = User::factory()->create();
        $employeeRecord = CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $applicant->id,
            'requested_job_title' => 'Dev',
            'status'              => 'approved',
            'role_id'             => $this->regularRole->id,
        ]);

        // Approve already approved -> 409
        $this->actingAs($this->hrUser)->postJson("/api/company/employees/{$employeeRecord->id}/approve", [
            'role_id' => $this->regularRole->id,
        ])->assertStatus(409);

        // Reject already approved -> 409
        $this->actingAs($this->hrUser)->postJson("/api/company/employees/{$employeeRecord->id}/reject", [
            'rejection_reason' => 'Already done',
        ])->assertStatus(409);
    }

    public function test_company_can_remove_employee_and_detaches_roles(): void
    {
        $targetUser = User::factory()->create(['account_type' => 'company']);
        $employeeRecord = CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $targetUser->id,
            'requested_job_title' => 'Software Engineer',
            'status'              => 'approved',
            'role_id'             => $this->regularRole->id,
            'joined_at'           => now()->subMonths(3),
        ]);

        $targetUser->roles()->attach($this->regularRole->id, [
            'company_id'  => $this->companyA->id,
            'assigned_at' => now()->subMonths(3),
        ]);

        $response = $this->actingAs($this->hrUser)->deleteJson("/api/company/employees/{$employeeRecord->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('employee.status', 'removed');
        $this->assertNotNull($response->json('employee.left_at'));

        $this->assertDatabaseHas('company_employees', [
            'id'     => $employeeRecord->id,
            'status' => 'removed',
        ]);

        // Role should be detached
        $this->assertDatabaseMissing('user_roles', [
            'user_id'    => $targetUser->id,
            'company_id' => $this->companyA->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'employee.removed',
            'user_id' => $this->hrUser->id,
        ]);
    }

    public function test_company_owner_cannot_be_removed_returns_403(): void
    {
        $ownerRecord = CompanyEmployee::where('user_id', $this->companyOwner->id)->firstOrFail();

        $response = $this->actingAs($this->hrUser)->deleteJson("/api/company/employees/{$ownerRecord->id}");

        $response->assertStatus(403);
        $this->assertStringContainsString('owner cannot be removed', $response->json('message'));
    }

    public function test_company_admin_cannot_remove_self_returns_403(): void
    {
        $hrRecord = CompanyEmployee::where('user_id', $this->hrUser->id)->firstOrFail();

        $response = $this->actingAs($this->hrUser)->deleteJson("/api/company/employees/{$hrRecord->id}");

        $response->assertStatus(403);
        $this->assertStringContainsString('cannot remove their own account', $response->json('message'));
    }

    public function test_cannot_manage_employee_of_another_company_returns_403(): void
    {
        // An employee in Company B
        $userB = User::factory()->create();
        $employeeB = CompanyEmployee::create([
            'company_id'          => $this->companyB->id,
            'user_id'             => $userB->id,
            'requested_job_title' => 'Dev B',
            'status'              => 'pending',
        ]);

        // HR of Company A tries to approve employee of Company B -> 403
        $respApprove = $this->actingAs($this->hrUser)->postJson("/api/company/employees/{$employeeB->id}/approve", [
            'role_id' => $this->regularRole->id,
        ]);
        $respApprove->assertStatus(403);

        // HR of Company A tries to reject employee of Company B -> 403
        $respReject = $this->actingAs($this->hrUser)->postJson("/api/company/employees/{$employeeB->id}/reject", [
            'rejection_reason' => 'Not our company',
        ]);
        $respReject->assertStatus(403);

        // HR of Company A tries to delete employee of Company B -> 403
        $respDelete = $this->actingAs($this->hrUser)->deleteJson("/api/company/employees/{$employeeB->id}");
        $respDelete->assertStatus(403);

        // HR of Company A tries to view employee of Company B -> 403
        $respShow = $this->actingAs($this->hrUser)->getJson("/api/company/employees/{$employeeB->id}");
        $respShow->assertStatus(403);
    }

    public function test_employee_without_permission_cannot_access_or_manage_returns_403(): void
    {
        $applicant = User::factory()->create();
        $employeeRecord = CompanyEmployee::create([
            'company_id'          => $this->companyA->id,
            'user_id'             => $applicant->id,
            'requested_job_title' => 'Dev',
            'status'              => 'pending',
        ]);

        // Regular employee has no employees.view permission -> 403
        $this->actingAs($this->regularEmployee)
            ->getJson('/api/company/employees')
            ->assertStatus(403);

        // Regular employee has no employees.approve permission -> 403
        $this->actingAs($this->regularEmployee)
            ->postJson("/api/company/employees/{$employeeRecord->id}/approve", ['role_id' => $this->regularRole->id])
            ->assertStatus(403);

        // Regular employee has no employees.manage permission -> 403
        $this->actingAs($this->regularEmployee)
            ->deleteJson("/api/company/employees/{$employeeRecord->id}")
            ->assertStatus(403);
    }

    public function test_user_can_reapply_after_join_request_rejected(): void
    {
        $applicant = User::factory()->create(['account_type' => 'company']);

        // 1. Submit initial request
        $resp1 = $this->actingAs($applicant)->postJson('/api/company/join-requests', [
            'company_id'           => $this->companyA->id,
            'requested_job_title'  => 'Junior Dev',
            'requested_department' => 'Engineering',
        ]);
        $resp1->assertStatus(201);
        $employeeId = $resp1->json('employee.id');

        // 2. Reject request
        $this->actingAs($this->hrUser)->postJson("/api/company/employees/{$employeeId}/reject", [
            'rejection_reason' => 'Not enough experience.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('company_employees', [
            'id'     => $employeeId,
            'status' => 'rejected',
        ]);

        // 3. User re-applies to Company A with different role
        $resp2 = $this->actingAs($applicant)->postJson('/api/company/join-requests', [
            'company_id'           => $this->companyA->id,
            'requested_job_title'  => 'Intern Dev',
            'requested_department' => 'Engineering',
        ]);

        $resp2->assertStatus(201);
        $this->assertEquals('pending', $resp2->json('employee.status'));
        $this->assertEquals('Intern Dev', $resp2->json('employee.requested_job_title'));
        $this->assertNull($resp2->json('employee.rejection_reason'));

        $this->assertDatabaseHas('company_employees', [
            'user_id'             => $applicant->id,
            'status'              => 'pending',
            'requested_job_title' => 'Intern Dev',
            'rejection_reason'    => null,
        ]);
    }

    public function test_user_can_join_another_company_after_being_removed(): void
    {
        $applicant = User::factory()->create(['account_type' => 'company']);

        // 1. Request to Company A
        $resp1 = $this->actingAs($applicant)->postJson('/api/company/join-requests', [
            'company_id'           => $this->companyA->id,
            'requested_job_title'  => 'Backend Engineer',
        ]);
        $resp1->assertStatus(201);
        $employeeId = $resp1->json('employee.id');

        // 2. Approve in Company A
        $this->actingAs($this->companyOwner)->postJson("/api/company/employees/{$employeeId}/approve", [
            'role_id' => $this->regularRole->id,
        ])->assertStatus(200);

        // 3. Remove from Company A
        $this->actingAs($this->companyOwner)->deleteJson("/api/company/employees/{$employeeId}")
            ->assertStatus(200);

        $this->assertDatabaseHas('company_employees', [
            'id'     => $employeeId,
            'status' => 'removed',
        ]);

        // 4. Join Company B
        $resp2 = $this->actingAs($applicant)->postJson('/api/company/join-requests', [
            'company_id'           => $this->companyB->id,
            'requested_job_title'  => 'Senior Backend Engineer',
        ]);

        $resp2->assertStatus(201);
        $this->assertEquals($this->companyB->id, $resp2->json('employee.company_id'));
        $this->assertEquals('pending', $resp2->json('employee.status'));

        $this->assertDatabaseHas('company_employees', [
            'user_id'    => $applicant->id,
            'company_id' => $this->companyB->id,
            'status'     => 'pending',
        ]);
    }
}
