<?php

namespace Tests\Feature\Admin;

use App\Models\AccountStatus;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewerAvailability;
use App\Models\Job;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewAndFixAuditTest extends TestCase
{
    use RefreshDatabase;

    // ─── Point 2: Two candidates booking same slot -> Second gets 409 ───

    public function test_two_candidates_booking_same_slot_second_gets_409(): void
    {
        $interviewer = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $interviewer->id, 'status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $cand1 = User::factory()->create(['account_type' => 'candidate']);
        $profile1 = CandidateProfile::create(['user_id' => $cand1->id]);
        $app1 = Application::create(['job_id' => $job->id, 'candidate_id' => $profile1->id, 'status' => 'interviewing']);

        $cand2 = User::factory()->create(['account_type' => 'candidate']);
        $profile2 = CandidateProfile::create(['user_id' => $cand2->id]);
        $app2 = Application::create(['job_id' => $job->id, 'candidate_id' => $profile2->id, 'status' => 'interviewing']);

        $slot = InterviewerAvailability::create([
            'interviewer_id'      => $interviewer->id,
            'start_at'            => Carbon::now()->addDays(5),
            'end_at'              => Carbon::now()->addDays(5)->addHour(),
            'availability_status' => 'open',
        ]);

        // Candidate 1 books successfully
        $resp1 = $this->actingAs($cand1)->postJson('/api/candidate/interviews/book', [
            'application_id'  => $app1->id,
            'availability_id' => $slot->id,
            'interview_type'  => 'online',
        ]);
        $resp1->assertStatus(201);
        $this->assertEquals('booked', $slot->fresh()->availability_status);

        // Candidate 2 attempts to book the same slot -> 409 Conflict
        $resp2 = $this->actingAs($cand2)->postJson('/api/candidate/interviews/book', [
            'application_id'  => $app2->id,
            'availability_id' => $slot->id,
            'interview_type'  => 'online',
        ]);
        $resp2->assertStatus(409);
        $resp2->assertJson(['message' => 'This slot is no longer available.']);
    }

    // ─── Point 4: Account Lifecycle & Inactive/Rejected Protections ───

    public function test_login_rejects_deactivated_user_with_403(): void
    {
        $user = User::factory()->create([
            'password'          => bcrypt('secret123'),
            'account_status_id' => AccountStatus::DEACTIVATED,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('inactive, suspended, or deleted', $response->json('message'));
    }

    public function test_updating_user_status_to_blocked_revokes_tokens(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $user = User::factory()->create(['account_status_id' => AccountStatus::ACTIVE]);

        // Generate Sanctum token for user
        $token = $user->createToken('test-token')->plainTextToken;
        $this->assertCount(1, $user->tokens);

        // Admin updates status to Blocked
        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$user->id}/status", [
            'account_status_id' => AccountStatus::BLOCKED,
            'reason'            => 'Fraudulent activity detected.',
        ]);

        $response->assertStatus(200);
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_rejected_company_employees_cannot_access_company_endpoints(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'status' => 'rejected']);

        $response = $this->actingAs($owner)->getJson('/api/company/jobs');
        $response->assertStatus(403);
        $this->assertStringContainsString('rejected', $response->json('message'));
    }

    public function test_suspended_company_employees_cannot_access_company_endpoints(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'status' => 'suspended']);

        $response = $this->actingAs($owner)->getJson('/api/company/jobs');
        $response->assertStatus(403);
        $this->assertStringContainsString('suspended', $response->json('message'));
    }

    // ─── Point 5: Multi-Tenant Boundary Protection ───────────────────

    public function test_hr_in_company_a_cannot_view_or_update_jobs_of_company_b(): void
    {
        $ownerA = User::factory()->create(['account_type' => 'company']);
        $companyA = Company::factory()->create(['owner_user_id' => $ownerA->id, 'status' => 'approved']);

        $ownerB = User::factory()->create(['account_type' => 'company']);
        $companyB = Company::factory()->create(['owner_user_id' => $ownerB->id, 'status' => 'approved']);

        $jobB = Job::factory()->create(['company_id' => $companyB->id, 'title' => 'Secret Job B']);

        // User from Company A tries to view Job B
        $responseShow = $this->actingAs($ownerA)->getJson("/api/company/jobs/{$jobB->id}");
        $responseShow->assertStatus(403);

        // User from Company A tries to update Job B
        $responseUpdate = $this->actingAs($ownerA)->putJson("/api/company/jobs/{$jobB->id}", [
            'title' => 'Hacked Title',
        ]);
        $responseUpdate->assertStatus(403);
    }

    public function test_hr_in_company_a_cannot_view_applications_of_company_b(): void
    {
        $ownerA = User::factory()->create(['account_type' => 'company']);
        $companyA = Company::factory()->create(['owner_user_id' => $ownerA->id, 'status' => 'approved']);

        $ownerB = User::factory()->create(['account_type' => 'company']);
        $companyB = Company::factory()->create(['owner_user_id' => $ownerB->id, 'status' => 'approved']);

        $jobB = Job::factory()->create(['company_id' => $companyB->id]);
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $appB = Application::create(['job_id' => $jobB->id, 'candidate_id' => $profile->id, 'status' => 'submitted']);

        // User A tries to view application of Job B
        $response = $this->actingAs($ownerA)->getJson("/api/company/applications/{$appB->id}");
        $response->assertStatus(403);

        // User A tries to list applications of Job B
        $responseList = $this->actingAs($ownerA)->getJson("/api/company/jobs/{$jobB->id}/applications");
        $responseList->assertStatus(403);
    }

    // ─── Point 6: AssignUserRoleRequest Scope Validation ─────────────

    public function test_platform_role_assignment_with_company_id_fails_422(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $user = User::factory()->create();
        $company = Company::factory()->create(['status' => 'approved']);

        $platformRole = Role::create([
            'name'  => 'super_admin_helper',
            'scope' => 'platform',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/roles", [
            'role_id'    => $platformRole->id,
            'company_id' => $company->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('company_id');
        $response->assertJsonFragment(['Platform-scoped roles cannot be associated with a company.']);
    }

    public function test_company_role_assignment_without_company_id_fails_422(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $user = User::factory()->create();

        $companyRole = Role::create([
            'name'  => 'hr_specialist',
            'scope' => 'company',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/roles", [
            'role_id' => $companyRole->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('company_id');
        $response->assertJsonFragment(['Company-scoped roles require a valid company_id.']);
    }

    // ─── Point 7: resolveCompanyId Admin Behavior ─────────────────────

    public function test_admin_accessing_company_endpoints_without_company_id_fails_422(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/company/jobs');
        $response->assertStatus(422);
        $this->assertStringContainsString('explicit company_id parameter or X-Company-Id header is required', $response->json('message'));
    }

    public function test_admin_accessing_company_endpoints_with_company_id_succeeds(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $company = Company::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($admin)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/company/jobs');

        $response->assertStatus(200);
    }

    // ─── Point 8: Company Status Allowed & Disallowed Transitions ────

    public function test_company_status_disallowed_transitions_fail_with_409(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);

        // 1. approved -> rejected (disallowed)
        $approvedCompany = Company::factory()->create(['status' => 'approved']);
        $resp1 = $this->actingAs($admin)->postJson("/api/admin/companies/{$approvedCompany->id}/reject", [
            'rejection_reason' => 'Too late',
        ]);
        $resp1->assertStatus(409);

        // 2. rejected -> approved (disallowed)
        $rejectedCompany = Company::factory()->create(['status' => 'rejected']);
        $resp2 = $this->actingAs($admin)->postJson("/api/admin/companies/{$rejectedCompany->id}/approve");
        $resp2->assertStatus(409);

        // 3. pending -> suspended (disallowed: must approve first)
        $pendingCompany = Company::factory()->create(['status' => 'pending']);
        $resp3 = $this->actingAs($admin)->postJson("/api/admin/companies/{$pendingCompany->id}/suspend");
        $resp3->assertStatus(409);

        // 4. suspended -> rejected (disallowed)
        $suspendedCompany = Company::factory()->create(['status' => 'suspended']);
        $resp4 = $this->actingAs($admin)->postJson("/api/admin/companies/{$suspendedCompany->id}/reject", [
            'rejection_reason' => 'Cannot reject suspended',
        ]);
        $resp4->assertStatus(409);
    }

    public function test_company_status_allowed_transitions_succeed(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);

        // pending -> approved
        $company = Company::factory()->create(['status' => 'pending']);
        $respApprove = $this->actingAs($admin)->postJson("/api/admin/companies/{$company->id}/approve");
        $respApprove->assertStatus(200);
        $this->assertEquals('approved', $company->fresh()->status);

        // approved -> suspended
        $respSuspend = $this->actingAs($admin)->postJson("/api/admin/companies/{$company->id}/suspend");
        $respSuspend->assertStatus(200);
        $this->assertEquals('suspended', $company->fresh()->status);

        // suspended -> approved (reactivation)
        $respReactivate = $this->actingAs($admin)->postJson("/api/admin/companies/{$company->id}/approve");
        $respReactivate->assertStatus(200);
        $this->assertEquals('approved', $company->fresh()->status);
    }
}
