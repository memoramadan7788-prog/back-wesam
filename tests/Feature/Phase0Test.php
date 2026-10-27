<?php

namespace Tests\Feature;

use App\Models\AccountStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Application;
use App\Models\CandidateCv;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\GradingResult;
use App\Models\InterviewBooking;
use App\Models\InterviewerAvailability;
use App\Models\Interview;
use App\Models\Job;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase0Test extends TestCase
{
    use RefreshDatabase;

    // ─── 0.1: company_id auto-detection ──────────────────────────────

    public function test_job_creation_uses_auth_user_company_not_request(): void
    {
        $user = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $user->id, 'status' => 'approved']);

        $response = $this->actingAs($user)->postJson('/api/company/jobs', [
            'title'           => 'Backend Developer',
            'description'     => 'We need a backend developer with strong PHP and Laravel skills.',
            'work_mode'       => 'remote',
            'employment_type' => 'full_time',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('jobs', [
            'company_id' => $company->id,
            'title'      => 'Backend Developer',
        ]);
    }

    public function test_user_cannot_create_job_for_another_company(): void
    {
        $userA = User::factory()->create(['account_type' => 'company']);
        $companyA = Company::factory()->create(['owner_user_id' => $userA->id, 'status' => 'approved']);

        $userB = User::factory()->create(['account_type' => 'company']);
        $companyB = Company::factory()->create(['owner_user_id' => $userB->id, 'status' => 'approved']);

        // UserA tries to pass companyB's ID – it should be ignored
        $response = $this->actingAs($userA)->postJson('/api/company/jobs', [
            'company_id'      => $companyB->id, // should be ignored
            'title'           => 'Spoofed Job',
            'description'     => 'Trying to create a job under another company account illegally.',
            'work_mode'       => 'remote',
            'employment_type' => 'full_time',
        ]);

        $response->assertStatus(201);
        // The job must belong to userA's company, NOT companyB
        $this->assertDatabaseHas('jobs', [
            'company_id' => $companyA->id,
            'title'      => 'Spoofed Job',
        ]);
        $this->assertDatabaseMissing('jobs', [
            'company_id' => $companyB->id,
            'title'      => 'Spoofed Job',
        ]);
    }

    public function test_exam_creation_uses_auth_user_company(): void
    {
        $user = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/company/exams', [
            'title'          => 'PHP Assessment',
            'time_limit_min' => 30,
            'passing_score'  => 60,
            'attempt_limit'  => 3,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('exams', [
            'company_id' => $company->id,
            'title'      => 'PHP Assessment',
        ]);
    }

    public function test_user_without_company_cannot_create_job(): void
    {
        $user = User::factory()->create(['account_type' => 'candidate']);

        $response = $this->actingAs($user)->postJson('/api/company/jobs', [
            'title'           => 'Should Fail',
            'description'     => 'This should not be allowed for users without a company.',
            'work_mode'       => 'remote',
            'employment_type' => 'full_time',
        ]);

        $response->assertStatus(403);
    }

    // ─── 0.2: Login & GET /api/auth/me enrichment ────────────────────

    public function test_me_returns_user_with_permissions(): void
    {
        $user = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        // Create role and permissions
        $perm = Permission::create(['key' => 'jobs.create', 'description' => 'Create jobs']);
        $role = Role::create(['name' => 'company_admin', 'scope' => 'company', 'description' => 'Admin']);
        $role->permissions()->attach($perm->id);
        $user->roles()->attach($role->id, ['company_id' => $company->id, 'assigned_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'account_type', 'roles', 'permissions', 'company'],
        ]);
        $response->assertJsonFragment(['jobs.create']);
    }

    public function test_login_returns_company_and_roles_and_permissions(): void
    {
        $user = User::factory()->create([
            'account_type' => 'company',
            'password'     => bcrypt('secret123'),
        ]);
        $company = Company::factory()->create(['owner_user_id' => $user->id, 'status' => 'approved']);

        $perm = Permission::create(['key' => 'jobs.create', 'description' => 'Create jobs']);
        $role = Role::create(['name' => 'company_admin', 'scope' => 'company', 'description' => 'Admin']);
        $role->permissions()->attach($perm->id);
        $user->roles()->attach($role->id, ['company_id' => $company->id, 'assigned_at' => now()]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.company.id', $company->id);
        $response->assertJsonFragment(['jobs.create']);
    }

    // ─── 0.3: interview_bookings re-booking after cancel ─────────────

    public function test_can_rebook_cancelled_interview_slot(): void
    {
        $interviewer = User::factory()->create();
        $candidate1 = User::factory()->create();
        $candidate2 = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $interviewer->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $profile1 = CandidateProfile::create(['user_id' => $candidate1->id]);
        $profile2 = CandidateProfile::create(['user_id' => $candidate2->id]);

        $app1 = Application::create(['job_id' => $job->id, 'candidate_id' => $profile1->id, 'status' => 'submitted']);
        $app2 = Application::create(['job_id' => $job->id, 'candidate_id' => $profile2->id, 'status' => 'submitted']);

        $slot = InterviewerAvailability::create([
            'interviewer_id'      => $interviewer->id,
            'start_at'            => now()->addDays(5),
            'end_at'              => now()->addDays(5)->addHour(),
            'availability_status' => 'open',
        ]);

        // First booking
        $interview1 = Interview::create([
            'application_id' => $app1->id,
            'round_no'       => 1,
            'interview_type' => 'online',
            'status'         => 'scheduled',
            'created_by'     => $interviewer->id,
        ]);

        $booking1 = InterviewBooking::create([
            'interview_id'   => $interview1->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at'       => $slot->start_at,
            'end_at'         => $slot->end_at,
            'booking_status' => 'confirmed',
        ]);

        $slot->update(['availability_status' => 'booked']);

        // Cancel the booking (soft-delete + release slot)
        $booking1->update([
            'booking_status' => 'cancelled',
            'cancelled_by'   => $interviewer->id,
            'cancelled_at'   => now(),
        ]);
        $booking1->delete(); // soft delete
        $slot->update(['availability_status' => 'open']);
        $interview1->update(['status' => 'cancelled']);

        // Second booking on same slot should succeed
        $interview2 = Interview::create([
            'application_id' => $app2->id,
            'round_no'       => 1,
            'interview_type' => 'online',
            'status'         => 'scheduled',
            'created_by'     => $interviewer->id,
        ]);

        $booking2 = InterviewBooking::create([
            'interview_id'   => $interview2->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at'       => $slot->start_at,
            'end_at'         => $slot->end_at,
            'booking_status' => 'confirmed',
        ]);

        $this->assertNotNull($booking2->id);
        $this->assertEquals('confirmed', $booking2->booking_status);
    }

    // ─── 0.5: Admin login uses users table ───────────────────────────

    public function test_admin_login_uses_dedicated_admins_table(): void
    {
        $admin = Admin::create([
            'name'         => 'Super Admin',
            'email'        => 'phase0-admin@example.com',
            'password'     => bcrypt('AdminPass123!'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);
        $superAdminRole = AdminRole::create([
            'name' => 'super_admin',
            'scope' => 'platform',
        ]);
        $admin->roles()->attach($superAdminRole->id, ['assigned_at' => now()]);

        $response = $this->postJson('/api/admin/login', [
            'email'    => $admin->email,
            'password' => 'AdminPass123!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Login successful.');
        $response->assertJsonPath('admin.id', $admin->id);
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonStructure(['access_token']);
    }

    public function test_non_admin_user_cannot_use_admin_login(): void
    {
        $user = User::factory()->create([
            'account_type' => 'candidate',
            'password'     => bcrypt('Password123!'),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401);
    }

    // ─── 0.6: Missing tests for prior fixes ──────────────────────────

    public function test_application_without_consent_returns_422(): void
    {
        $candidate = User::factory()->create();
        CandidateProfile::create(['user_id' => $candidate->id]);
        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id, 'status' => 'published', 'visibility' => 'public']);

        // Without consent field
        $response = $this->actingAs($candidate)->postJson("/api/jobs/{$job->id}/apply", [
            'cover_note' => 'I want to apply.',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('consent');

        // With consent = false
        $response2 = $this->actingAs($candidate)->postJson("/api/jobs/{$job->id}/apply", [
            'cover_note' => 'I want to apply.',
            'consent'    => false,
        ]);
        $response2->assertStatus(422);
    }

    public function test_pending_company_posting_job_returns_403(): void
    {
        $user = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'status'        => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson('/api/company/jobs', [
            'title'           => 'Should Be Denied',
            'description'     => 'This job posting should be rejected because company is pending.',
            'work_mode'       => 'remote',
            'employment_type' => 'full_time',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('pending', $response->json('message'));
    }

    public function test_exam_with_written_question_returns_pending_review(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $candidate = User::factory()->create();
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $profile->id,
            'status'       => 'submitted',
        ]);

        $exam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $companyOwner->id,
            'title'          => 'Mixed Exam',
            'time_limit_min' => 30,
            'passing_score'  => 50,
            'attempt_limit'  => 3,
            'status'         => 'published',
        ]);

        // MCQ question
        $q1 = Question::create([
            'exam_id' => $exam->id, 'question_type' => 'mcq',
            'content' => 'Pick one', 'points' => 50, 'order_no' => 1, 'status' => 'published',
        ]);
        $opt = QuestionOption::create(['question_id' => $q1->id, 'option_text' => 'Correct', 'is_correct' => true]);

        // Written question (requires manual grading)
        $q2 = Question::create([
            'exam_id' => $exam->id, 'question_type' => 'written',
            'content' => 'Explain SOLID', 'points' => 50, 'order_no' => 2, 'status' => 'published',
        ]);

        $attempt = ExamAttempt::create([
            'exam_id'        => $exam->id,
            'application_id' => $application->id,
            'attempt_no'     => 1,
            'status'         => 'in_progress',
            'started_at'     => now(),
        ]);

        $response = $this->actingAs($candidate)->postJson("/api/candidate/exam-attempts/{$attempt->id}/submit", [
            'answers' => [
                ['question_id' => $q1->id, 'selected_option_id' => $opt->id],
                ['question_id' => $q2->id, 'answer_text' => 'SOLID principles explanation...'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('grading_status', 'pending_review');
        $response->assertJsonPath('is_passed', null);
    }
}
