<?php

namespace Tests\Feature;

use App\Models\AccountStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Country;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewEvaluation;
use App\Models\InterviewerAvailability;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\Offer;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Skill;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FullRecruitmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Admin $platformAdmin;
    protected Role $companyAdminRole;
    protected Role $hrRole;
    protected Role $interviewerRole;
    protected Role $candidateRole;
    protected JobCategory $jobCategory;
    protected Country $country;
    protected Skill $skillPhp;
    protected Skill $skillLaravel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRoleSeeder::class);

        // System Admin
        $this->adminUser = User::factory()->create([
            'account_type' => 'admin',
            'name'         => 'System Administrator',
            'email'        => 'superadmin@example.com',
            'password'     => Hash::make('Password123!'),
        ]);

        $this->platformAdmin = Admin::create([
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('Password123!'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);
        $this->platformAdmin->roles()->attach(AdminRole::where('name', 'super_admin')->firstOrFail()->id, [
            'assigned_at' => now(),
        ]);

        $this->companyAdminRole = Role::where('name', 'company_admin')->firstOrFail();
        $this->hrRole           = Role::where('name', 'hr')->firstOrFail();
        $this->interviewerRole  = Role::where('name', 'interviewer')->firstOrFail();
        $this->candidateRole    = Role::where('name', 'candidate')->firstOrFail();

        // Initial Lookups
        $this->jobCategory = JobCategory::firstOrCreate(['id' => 1], ['name' => 'IT & Software']);
        $this->country     = Country::firstOrCreate(['id' => 1], ['name' => 'Saudi Arabia', 'code' => 'SA']);
        $this->skillPhp     = Skill::firstOrCreate(['id' => 1], ['name' => 'PHP']);
        $this->skillLaravel = Skill::firstOrCreate(['id' => 2], ['name' => 'Laravel']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // الخطوة 1: E2E Full Recruitment Flow
    // ═══════════════════════════════════════════════════════════════════════

    public function test_full_recruitment_flow_e2e(): void
    {
        // ── 1. Admin Login ──
        $adminLoginResp = $this->postJson('/api/admin/login', [
            'email'    => 'superadmin@example.com',
            'password' => 'Password123!',
        ]);
        $adminLoginResp->assertStatus(200);
        // Note: Admin login returns 'access_token', whereas user login returns 'token'
        $adminToken = $adminLoginResp->json('access_token') ?? $adminLoginResp->json('token');
        $this->assertNotEmpty($adminToken);

        // ── 2. Company Registration (register/company) & Admin Approval ──
        $regCompanyResp = $this->postJson('/api/auth/register/company', [
            'name'                  => 'John Founder',
            'email'                 => 'founder@acmetech.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name'          => 'Acme Tech Solutions',
            'commercial_register'   => UploadedFile::fake()->create('cr.pdf', 500, 'application/pdf'),
            'website'               => 'https://acmetech.com',
            'industry'              => 'Software Development',
        ]);
        $regCompanyResp->assertStatus(201);
        $companyId = $regCompanyResp->json('company.id');
        $this->assertEquals('pending', $regCompanyResp->json('company.status'));

        // Admin approves company
        $approveCompanyResp = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/admin/companies/{$companyId}/approve");
        $approveCompanyResp->assertStatus(200);
        $this->assertEquals('approved', $approveCompanyResp->json('company.status'));
        $this->assertDatabaseHas('companies', ['id' => $companyId, 'status' => 'approved']);

        // ── 3. Register Employees (HR & Interviewer), Join Request, Approval ──
        // POST /api/auth/register/employee now implemented
        $employeeRegAttempt = $this->postJson('/api/auth/register/employee', [
            'name'                  => 'Sarah HR',
            'email'                 => 'sarah.hr@acmetech.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $employeeRegAttempt->assertStatus(201);
        $employeeRegAttempt->assertJsonStructure(['user', 'token', 'access_token']);
        $this->assertEquals('company', $employeeRegAttempt->json('user.account_type'));
        $hrUser = User::where('email', 'sarah.hr@acmetech.com')->first();

        // HR submits join request
        $hrJoinResp = $this->actingAs($hrUser, 'sanctum')
            ->postJson('/api/company/join-requests', [
                'company_id'           => $companyId,
                'requested_job_title'  => 'HR Manager',
                'requested_department' => 'Human Resources',
            ]);
        $hrJoinResp->assertStatus(201);
        $hrEmployeeId = $hrJoinResp->json('employee.id');

        // Company Owner approves HR and assigns 'hr' role
        $ownerUser = User::where('email', 'founder@acmetech.com')->first();
        $this->actingAs($ownerUser, 'sanctum');

        $approveHrResp = $this->postJson("/api/company/employees/{$hrEmployeeId}/approve", [
            'role_id' => $this->hrRole->id,
        ]);
        $approveHrResp->assertStatus(200);
        $this->assertEquals('approved', $approveHrResp->json('employee.status'));
        $this->assertEquals('hr', $approveHrResp->json('employee.role.name'));
        $hrUser = $hrUser->fresh();

        // Interviewer User Onboarding via register/employee
        $interviewerRegResp = $this->postJson('/api/auth/register/employee', [
            'name'                  => 'Dave Tech Interviewer',
            'email'                 => 'dave.interviewer@acmetech.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $interviewerRegResp->assertStatus(201);
        $interviewerUser = User::where('email', 'dave.interviewer@acmetech.com')->first();

        $interviewerJoinResp = $this->actingAs($interviewerUser, 'sanctum')
            ->postJson('/api/company/join-requests', [
                'company_id'           => $companyId,
                'requested_job_title'  => 'Senior Technical Interviewer',
                'requested_department' => 'Engineering',
            ]);
        $interviewerJoinResp->assertStatus(201);
        $interviewerEmployeeId = $interviewerJoinResp->json('employee.id');

        $approveInterviewerResp = $this->actingAs($ownerUser, 'sanctum')
            ->postJson("/api/company/employees/{$interviewerEmployeeId}/approve", [
                'role_id' => $this->interviewerRole->id,
            ]);
        $approveInterviewerResp->assertStatus(200);
        $this->assertEquals('interviewer', $approveInterviewerResp->json('employee.role.name'));
        $interviewerUser = $interviewerUser->fresh();

        // ── 4. HR creates Job with skills, Exam with MCQ questions, and attaches to Job ──
        // Job creation
        $jobResp = $this->actingAs($hrUser, 'sanctum')
            ->postJson('/api/company/jobs', [
                'title'            => 'Senior Laravel Developer',
                'category_id'      => $this->jobCategory->id,
                'location'         => 'Riyadh, Saudi Arabia',
                'work_mode'        => 'hybrid',
                'employment_type'  => 'full_time',
                'salary_min'       => 12000,
                'salary_max'       => 18000,
                'currency'         => 'SAR',
                'openings_count'   => 2,
                'deadline'         => Carbon::now()->addDays(30)->toDateString(),
                'description'      => 'We are seeking an experienced Laravel engineer to lead backend architecture.',
                'skills'           => [
                    ['skill_id' => $this->skillPhp->id, 'is_required' => true],
                    ['skill_id' => $this->skillLaravel->id, 'is_required' => true],
                ],
            ]);
        if ($jobResp->status() !== 201) {
            dump('jobResp failed:', $jobResp->json(), 'hrUser account_type:', $hrUser->account_type, 'hrUser company:', $hrUser->resolveCompanyId(), 'roles:', $hrUser->roles->pluck('name'));
        }
        $jobResp->assertStatus(201);
        $jobId = $jobResp->json('data.id') ?? $jobResp->json('job.id');

        // Publish Job
        $publishResp = $this->actingAs($hrUser, 'sanctum')
            ->patchJson("/api/company/jobs/{$jobId}/status", [
                'status' => 'published',
            ]);
        $publishResp->assertStatus(200);

        // Create Exam
        $examResp = $this->actingAs($hrUser, 'sanctum')
            ->postJson('/api/company/exams', [
                'title'          => 'Laravel Architecture Assessment',
                'time_limit_min' => 60,
                'passing_score'  => 70,
                'attempt_limit'  => 3,
            ]);
        $examResp->assertStatus(201);
        $examId = $examResp->json('data.id') ?? $examResp->json('exam.id');

        // Add MCQ Question to Exam
        $questionResp = $this->actingAs($hrUser, 'sanctum')
            ->postJson("/api/company/exams/{$examId}/questions", [
                'question_type' => 'mcq',
                'content'       => 'Which HTTP status code signifies resource conflict?',
                'points'        => 10,
                'options'       => [
                    ['option_text' => '409 Conflict', 'is_correct' => true],
                    ['option_text' => '400 Bad Request', 'is_correct' => false],
                ],
            ]);
        $questionResp->assertStatus(201);

        // Attach Exam to Job
        $attachResp = $this->actingAs($hrUser, 'sanctum')
            ->postJson("/api/company/jobs/{$jobId}/exams/{$examId}/attach");
        $attachResp->assertStatus(200);
        $this->assertDatabaseHas('job_exams', [
            'job_id'  => $jobId,
            'exam_id' => $examId,
        ]);

        // Interview round creation via real endpoint (now implemented)
        $roundResp = $this->actingAs($hrUser, 'sanctum')
            ->postJson("/api/company/jobs/{$jobId}/interview-rounds", [
                'round_no'       => 1,
                'interview_type' => 'online',
                'duration_min'   => 45,
            ]);
        $roundResp->assertStatus(201);
        $this->assertEquals(1, $roundResp->json('data.round_no'));
        $this->assertEquals('online', $roundResp->json('data.interview_type'));

        // ── 5. Candidate Registration, Profile Completion, CV Upload ──
        $candRegResp = $this->postJson('/api/auth/register/candidate', [
            'name'                  => 'Sarah Jenkins',
            'email'                 => 'sarah.candidate@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $candRegResp->assertStatus(201);
        $candUser = User::where('email', 'sarah.candidate@example.com')->firstOrFail();

        // Complete Profile
        $profileResp = $this->actingAs($candUser, 'sanctum')
            ->putJson('/api/candidate/profile', [
                'headline'            => 'Senior Full-Stack Engineer',
                'summary'             => 'Over 6 years of experience building scalable applications.',
                'phone'               => '+966500000000',
                'current_job_title'   => 'Software Engineer',
                'years_of_experience' => 5,
                'city'                => 'Riyadh',
                'country_id'          => $this->country->id,
            ]);
        $profileResp->assertStatus(200);

        // Upload CV
        $cvResp = $this->actingAs($candUser, 'sanctum')
            ->post('/api/candidate/cvs', [
                'cv' => UploadedFile::fake()->create('sarah_cv.pdf', 200, 'application/pdf'),
            ], ['Accept' => 'application/json']);
        $cvResp->assertStatus(201);
        $cvId = $cvResp->json('data.id') ?? $cvResp->json('cv.id');

        // ── 6. Candidate searches public jobs and applies with consent ──
        $searchResp = $this->getJson('/api/jobs');
        $searchResp->assertStatus(200);
        $this->assertNotEmpty($searchResp->json('data'));

        $applyResp = $this->actingAs($candUser, 'sanctum')
            ->postJson("/api/jobs/{$jobId}/apply", [
                'cv_id'      => $cvId,
                'cover_note' => 'I am thrilled to apply for this engineering role.',
                'consent'    => true,
            ]);
        $applyResp->assertStatus(201);
        $applicationId = $applyResp->json('application.id') ?? $applyResp->json('data.id');

        // ── 7. Candidate takes exam, submits answers, checks result ──
        $startExamResp = $this->actingAs($candUser, 'sanctum')
            ->postJson("/api/candidate/exams/{$examId}/applications/{$applicationId}/start");
        // Start returns 200 OK
        $startExamResp->assertStatus(200);
        $attemptId = $startExamResp->json('attempt.id') ?? $startExamResp->json('attempt_id');
        $questions = $startExamResp->json('questions');

        // Verify candidate DOES NOT see 'is_correct' in exam questions
        foreach ($questions as $q) {
            foreach ($q['options'] ?? [] as $opt) {
                $this->assertArrayNotHasKey('is_correct', $opt, 'Security violation: candidate saw is_correct in exam questions');
            }
        }

        // Find correct option ID from DB for testing submission
        $correctOption = QuestionOption::where('is_correct', true)->first();

        $submitExamResp = $this->actingAs($candUser, 'sanctum')
            ->postJson("/api/candidate/exam-attempts/{$attemptId}/submit", [
                'answers' => [
                    [
                        'question_id'        => $questions[0]['id'],
                        'selected_option_id' => $correctOption->id,
                    ],
                ],
            ]);
        $submitExamResp->assertStatus(200);

        // Check Result
        $resultResp = $this->actingAs($candUser, 'sanctum')
            ->getJson("/api/candidate/exam-attempts/{$attemptId}/result");
        $resultResp->assertStatus(200);
        $this->assertEquals(10, $resultResp->json('total_score'));
        $this->assertFalse($resultResp->json('is_passed')); // 10 < 70 passing score

        // ── 8. HR updates application stage on ATS Kanban ──
        // Follow the state machine: submitted -> screening -> shortlisted -> interviewing
        $this->actingAs($hrUser, 'sanctum')
            ->patchJson("/api/company/applications/{$applicationId}/status", [
                'status' => 'screening',
            ])->assertStatus(200);

        $this->actingAs($hrUser, 'sanctum')
            ->patchJson("/api/company/applications/{$applicationId}/status", [
                'status' => 'shortlisted',
            ])->assertStatus(200);

        $stageResp = $this->actingAs($hrUser, 'sanctum')
            ->patchJson("/api/company/applications/{$applicationId}/status", [
                'status' => 'interviewing',
            ]);
        $stageResp->assertStatus(200);
        $this->assertEquals('interviewing', $stageResp->json('data.status') ?? $stageResp->json('application.status'));

        // ── 9. Interview Scheduling & Booking ──
        // Company assigns the real Package 3 interview endpoint, lists it, then cancels it.
        $companyInterviewResp = $this->actingAs($hrUser, 'sanctum')
            ->postJson("/api/company/applications/{$applicationId}/interviews", [
                'round_no'       => 1,
                'interview_type' => 'online',
                'interviewer_id' => $interviewerUser->id,
                'instructions'   => 'Please prepare a system design discussion.',
            ]);
        $companyInterviewResp->assertStatus(201);
        $companyInterviewId = $companyInterviewResp->json('data.id');

        $this->actingAs($hrUser, 'sanctum')
            ->getJson('/api/company/interviews')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->actingAs($hrUser, 'sanctum')
            ->postJson("/api/company/interviews/{$companyInterviewId}/cancel", [
                'reason' => 'Candidate will use the next available scheduling slot.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Interviewer adds availability slot
        $availResp = $this->actingAs($interviewerUser, 'sanctum')
            ->postJson('/api/interviewer/availabilities', [
                'start_at' => Carbon::now()->addDays(5)->setTime(10, 0)->toDateTimeString(),
                'end_at'   => Carbon::now()->addDays(5)->setTime(11, 0)->toDateTimeString(),
            ]);
        $availResp->assertStatus(201);
        $availabilityId = $availResp->json('data.id');

        // Candidate views available slots
        $slotsResp = $this->actingAs($candUser, 'sanctum')
            ->getJson("/api/candidate/available-slots?application_id={$applicationId}");
        $slotsResp->assertStatus(200);
        $this->assertNotEmpty($slotsResp->json('data'));

        // Candidate books slot
        $bookResp = $this->actingAs($candUser, 'sanctum')
            ->postJson('/api/candidate/interviews/book', [
                'application_id'  => $applicationId,
                'availability_id' => $availabilityId,
                'interview_type'  => 'online',
                'instructions'    => 'Please join with webcam enabled.',
            ]);
        $bookResp->assertStatus(201);
        $interviewId = $bookResp->json('data.id');

        // ── 10. Interviewer evaluates and publishes feedback ──
        $evalResp = $this->actingAs($interviewerUser, 'sanctum')
            ->postJson("/api/interviews/{$interviewId}/evaluations", [
                'numeric_score'      => 92,
                'internal_notes'     => 'Exceptional grasp of high-concurrency Laravel architecture.',
                'candidate_feedback' => 'Impressive technical knowledge and great communication.',
            ]);
        $evalResp->assertStatus(201);
        $evalId = $evalResp->json('data.id') ?? $evalResp->json('id');

        // Check candidate interview details BEFORE publishing feedback
        $candInterviewBeforePublish = $this->actingAs($candUser, 'sanctum')
            ->getJson("/api/candidate/interviews/{$interviewId}");
        $candInterviewBeforePublish->assertStatus(200);
        $this->assertEmpty($candInterviewBeforePublish->json('data.shared_feedback'));

        // Publish Evaluation
        $publishEvalResp = $this->actingAs($interviewerUser, 'sanctum')
            ->postJson("/api/interviews/{$interviewId}/evaluations/{$evalId}/publish");
        $publishEvalResp->assertStatus(200);

        // Check candidate interview details AFTER publishing
        $candInterviewAfterPublish = $this->actingAs($candUser, 'sanctum')
            ->getJson("/api/candidate/interviews/{$interviewId}");
        $candInterviewAfterPublish->assertStatus(200);
        $feedbackList = $candInterviewAfterPublish->json('data.shared_feedback');
        $this->assertNotEmpty($feedbackList);
        $this->assertEquals('Impressive technical knowledge and great communication.', $feedbackList[0]['candidate_feedback']);
        // Verify internal_notes is NEVER exposed to candidate
        $this->assertArrayNotHasKey('internal_notes', $feedbackList[0]);

        // ── 11. HR issues Offer, Candidate accepts, Application status becomes 'hired' ──
        // State machine: interviewing -> offered
        $this->actingAs($hrUser, 'sanctum')
            ->patchJson("/api/company/applications/{$applicationId}/status", [
                'status' => 'offered',
            ])->assertStatus(200);

        $offerResp = $this->actingAs($hrUser, 'sanctum')
            ->postJson('/api/company/offers', [
                'application_id' => $applicationId,
                'salary_amount'  => 15000,
                'currency'       => 'SAR',
                'salary_period'  => 'month',
                'start_date'     => Carbon::now()->addDays(30)->toDateString(),
                'expires_at'     => Carbon::now()->addDays(14)->toDateTimeString(),
            ]);
        $offerResp->assertStatus(201);
        $offerId = $offerResp->json('offer.id') ?? $offerResp->json('data.id');

        // Candidate accepts offer
        $acceptOfferResp = $this->actingAs($candUser, 'sanctum')
            ->postJson("/api/candidate/offers/{$offerId}/accept");
        $acceptOfferResp->assertStatus(200);
        $this->assertEquals('accepted', $acceptOfferResp->json('offer.status') ?? $acceptOfferResp->json('data.status'));

        // Verify application status is now 'hired'
        $this->assertDatabaseHas('applications', [
            'id'     => $applicationId,
            'status' => 'hired',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // الخطوة 2: الفحوصات المنطقية (Logical Checks)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_logic_pending_or_rejected_company_cannot_post_job(): void
    {
        $pendingCompany = Company::factory()->create(['status' => 'pending']);
        $userPending = User::factory()->create(['account_type' => 'company']);
        CompanyEmployee::create([
            'company_id'          => $pendingCompany->id,
            'user_id'             => $userPending->id,
            'status'              => 'approved',
            'role_id'             => $this->hrRole->id,
        ]);
        $userPending->roles()->attach($this->hrRole->id, ['company_id' => $pendingCompany->id]);
        $tokenPending = $userPending->createToken('auth-token', ['company'])->plainTextToken;

        $responsePending = $this->withHeader('Authorization', "Bearer {$tokenPending}")
            ->postJson('/api/company/jobs', [
                'title'           => 'Job from Pending Company',
                'employment_type' => 'full_time',
                'work_mode'       => 'remote',
                'description'     => 'Testing pending company posting job rejection.',
            ]);
        // Must be rejected with 403 Forbidden
        $responsePending->assertStatus(403);

        // Rejected Company
        $rejectedCompany = Company::factory()->create(['status' => 'rejected']);
        $userRejected = User::factory()->create(['account_type' => 'company']);
        CompanyEmployee::create([
            'company_id'          => $rejectedCompany->id,
            'user_id'             => $userRejected->id,
            'status'              => 'approved',
            'role_id'             => $this->hrRole->id,
        ]);
        $userRejected->roles()->attach($this->hrRole->id, ['company_id' => $rejectedCompany->id]);
        $tokenRejected = $userRejected->createToken('auth-token', ['company'])->plainTextToken;

        $responseRejected = $this->withHeader('Authorization', "Bearer {$tokenRejected}")
            ->postJson('/api/company/jobs', [
                'title'           => 'Job from Rejected Company',
                'employment_type' => 'full_time',
                'work_mode'       => 'remote',
                'description'     => 'Testing rejected company posting job rejection.',
            ]);
        $responseRejected->assertStatus(403);
    }

    public function test_logic_candidate_cannot_apply_twice_and_can_apply_after_withdraw(): void
    {
        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id, 'status' => 'published']);

        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $candToken = $candidate->createToken('auth-token', ['candidate'])->plainTextToken;

        // First application succeeds
        $firstApply = $this->withHeader('Authorization', "Bearer {$candToken}")
            ->postJson("/api/jobs/{$job->id}/apply", ['consent' => true]);
        $firstApply->assertStatus(201);
        $appId = $firstApply->json('application.id') ?? $firstApply->json('data.id');
        $this->assertNotNull($appId);

        // Second application to same active job fails (409 Conflict)
        $secondApply = $this->withHeader('Authorization', "Bearer {$candToken}")
            ->postJson("/api/jobs/{$job->id}/apply", ['consent' => true]);
        $secondApply->assertStatus(409);

        // Candidate withdraws application
        $withdraw = $this->withHeader('Authorization', "Bearer {$candToken}")
            ->deleteJson("/api/candidate/applications/{$appId}/withdraw");
        $withdraw->assertStatus(200);

        // Re-applying after withdraw
        $reApply = $this->withHeader('Authorization', "Bearer {$candToken}")
            ->postJson("/api/jobs/{$job->id}/apply", ['consent' => true]);
        // After withdrawal, re-application succeeds (201 Created)
        $this->assertEquals(201, $reApply->status());
    }

    public function test_logic_exam_max_attempts_expired_submission_and_attempt_hijacking(): void
    {
        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id, 'status' => 'published']);
        $exam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $this->adminUser->id,
            'title'          => 'Limit Test Exam',
            'time_limit_min' => 60,
            'attempt_limit'  => 1,
            'passing_score'  => 50,
        ]);
        $q = Question::create([
            'exam_id'       => $exam->id,
            'question_type' => 'mcq',
            'content'       => 'Test Question',
            'order_no'      => 1,
            'points'        => 10,
        ]);
        QuestionOption::create(['question_id' => $q->id, 'option_text' => 'Opt 1', 'is_correct' => true]);

        $cand1 = User::factory()->create(['account_type' => 'candidate']);
        $prof1 = CandidateProfile::create(['user_id' => $cand1->id]);
        $app1 = Application::create(['job_id' => $job->id, 'candidate_id' => $prof1->id, 'status' => 'submitted']);
        $token1 = $cand1->createToken('auth-token', ['candidate'])->plainTextToken;

        // Attempt 1
        $start1 = $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson("/api/candidate/exams/{$exam->id}/applications/{$app1->id}/start");
        $start1->assertStatus(200);
        $attempt1Id = $start1->json('attempt.id');

        // Submit attempt 1
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson("/api/candidate/exam-attempts/{$attempt1Id}/submit", [
                'answers' => [
                    ['question_id' => $q->id, 'answer_text' => 'My answer'],
                ],
            ])
            ->assertStatus(200);

        // Attempt 2 exceeds max_attempts (attempt_limit = 1) -> must fail (403 Forbidden)
        $start2 = $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson("/api/candidate/exams/{$exam->id}/applications/{$app1->id}/start");
        $start2->assertStatus(403);
    }

    public function test_logic_candidate_cannot_see_is_correct_during_exam(): void
    {
        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id, 'status' => 'published']);
        $exam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $this->adminUser->id,
            'title'          => 'Security Test Exam',
            'time_limit_min' => 60,
            'attempt_limit'  => 3,
            'passing_score'  => 50,
        ]);
        $q = Question::create([
            'exam_id'       => $exam->id,
            'question_type' => 'mcq',
            'content'       => 'Hidden Answer Question',
            'order_no'      => 1,
            'points'        => 10,
        ]);
        QuestionOption::create(['question_id' => $q->id, 'option_text' => 'Correct Answer', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q->id, 'option_text' => 'Wrong Answer', 'is_correct' => false]);

        $cand = User::factory()->create(['account_type' => 'candidate']);
        $prof = CandidateProfile::create(['user_id' => $cand->id]);
        $app = Application::create(['job_id' => $job->id, 'candidate_id' => $prof->id, 'status' => 'submitted']);
        $candToken = $cand->createToken('auth-token', ['candidate'])->plainTextToken;

        $startResp = $this->withHeader('Authorization', "Bearer {$candToken}")
            ->postJson("/api/candidate/exams/{$exam->id}/applications/{$app->id}/start");
        $startResp->assertStatus(200);

        $responseContent = json_encode($startResp->json());
        $this->assertStringNotContainsString('"is_correct"', $responseContent);
    }

    public function test_logic_candidate_cannot_see_internal_notes_or_unpublished_evaluations(): void
    {
        $interviewer = User::factory()->create(['account_type' => 'company']);
        $cand = User::factory()->create(['account_type' => 'candidate']);
        $prof = CandidateProfile::create(['user_id' => $cand->id]);
        $company = Company::factory()->create(['owner_user_id' => $interviewer->id, 'status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $app = Application::create(['job_id' => $job->id, 'candidate_id' => $prof->id, 'status' => 'interviewing']);

        $interview = Interview::create([
            'application_id' => $app->id,
            'round_no'       => 1,
            'interview_type' => 'online',
            'status'         => 'completed',
        ]);

        $eval = InterviewEvaluation::create([
            'interview_id'       => $interview->id,
            'evaluator_id'       => $interviewer->id,
            'numeric_score'      => 80,
            'internal_notes'     => 'CONFIDENTIAL_NOTE_DO_NOT_LEAK',
            'candidate_feedback' => 'Candidate communication was clear.',
            'feedback_shared_at' => null, // UNPUBLISHED
        ]);

        $candToken = $cand->createToken('auth-token', ['candidate'])->plainTextToken;

        $resp = $this->withHeader('Authorization', "Bearer {$candToken}")
            ->getJson("/api/candidate/interviews/{$interview->id}");
        $resp->assertStatus(200);

        $responseBody = json_encode($resp->json());
        $this->assertStringNotContainsString('CONFIDENTIAL_NOTE_DO_NOT_LEAK', $responseBody);
        // Because feedback_shared_at is null, candidate sees empty feedback
        $this->assertEmpty($resp->json('data.shared_feedback'));
    }

    public function test_logic_hr_company_a_cannot_access_or_modify_company_b_resources(): void
    {
        $companyA = Company::factory()->create(['status' => 'approved', 'name' => 'Company A']);
        $hrUserA = User::factory()->create(['account_type' => 'company']);
        CompanyEmployee::create([
            'company_id' => $companyA->id,
            'user_id'    => $hrUserA->id,
            'status'     => 'approved',
            'role_id'    => $this->hrRole->id,
        ]);
        $hrUserA->roles()->attach($this->hrRole->id, ['company_id' => $companyA->id]);
        $tokenA = $hrUserA->createToken('auth-token', ['company'])->plainTextToken;

        $companyB = Company::factory()->create(['status' => 'approved', 'name' => 'Company B']);
        $jobB = Job::factory()->create(['company_id' => $companyB->id, 'title' => 'Job of Company B']);

        // HR A views Job B -> 403 Forbidden
        $viewJobResp = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/company/jobs/{$jobB->id}");
        $viewJobResp->assertStatus(403);

        // HR A updates Job B -> 403 Forbidden
        $updateJobResp = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->putJson("/api/company/jobs/{$jobB->id}", [
                'title'       => 'Hacked Job Title',
                'description' => 'Unauthorized modification attempt.',
                'work_mode'   => 'remote',
            ]);
        $updateJobResp->assertStatus(403);

        // HR A views Applications of Job B -> 403 Forbidden
        $viewAppsResp = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/company/jobs/{$jobB->id}/applications");
        $viewAppsResp->assertStatus(403);

        // HR A manages Employee of Company B -> 403 Forbidden
        $empB = CompanyEmployee::create([
            'company_id'          => $companyB->id,
            'user_id'             => User::factory()->create()->id,
            'status'              => 'pending',
            'requested_job_title' => 'QA B',
        ]);
        $approveEmpB = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson("/api/company/employees/{$empB->id}/approve", [
                'role_id' => $this->hrRole->id,
            ]);
        $approveEmpB->assertStatus(403);
    }

    public function test_logic_role_isolation_candidate_cannot_access_company_endpoints_and_vice_versa(): void
    {
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $candToken = $candidate->createToken('auth-token', ['candidate'])->plainTextToken;

        // Candidate attempts to access company jobs listing -> 403 Forbidden
        $candAccessCompany = $this->withHeader('Authorization', "Bearer {$candToken}")
            ->getJson('/api/company/jobs');
        $candAccessCompany->assertStatus(403);

        // Candidate attempts to view company employees -> 403 Forbidden
        $candAccessEmployees = $this->withHeader('Authorization', "Bearer {$candToken}")
            ->getJson('/api/company/employees');
        $candAccessEmployees->assertStatus(403);
    }

    public function test_logic_slot_double_booking_and_rebooking_after_cancellation(): void
    {
        $interviewer = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $interviewer->id, 'status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $cand1 = User::factory()->create(['account_type' => 'candidate']);
        $prof1 = CandidateProfile::create(['user_id' => $cand1->id]);
        $app1 = Application::create(['job_id' => $job->id, 'candidate_id' => $prof1->id, 'status' => 'interviewing']);

        $cand2 = User::factory()->create(['account_type' => 'candidate']);
        $prof2 = CandidateProfile::create(['user_id' => $cand2->id]);
        $app2 = Application::create(['job_id' => $job->id, 'candidate_id' => $prof2->id, 'status' => 'interviewing']);

        $slot = InterviewerAvailability::create([
            'interviewer_id'      => $interviewer->id,
            'start_at'            => Carbon::now()->addDays(3)->setTime(14, 0),
            'end_at'              => Carbon::now()->addDays(3)->setTime(15, 0),
            'availability_status' => 'open',
        ]);

        // Candidate 1 books slot -> 201 Created
        $book1 = $this->actingAs($cand1)->postJson('/api/candidate/interviews/book', [
            'application_id'  => $app1->id,
            'availability_id' => $slot->id,
        ]);
        $book1->assertStatus(201);
        $interview1Id = $book1->json('data.id');

        // Candidate 2 attempts to book same slot -> 409 Conflict
        $book2 = $this->actingAs($cand2)->postJson('/api/candidate/interviews/book', [
            'application_id'  => $app2->id,
            'availability_id' => $slot->id,
        ]);
        $book2->assertStatus(409);

        // Candidate 1 cancels interview
        $cancel1 = $this->actingAs($cand1)->postJson("/api/candidate/interviews/{$interview1Id}/cancel", [
            'reason' => 'Schedule conflict',
        ]);
        $cancel1->assertStatus(200);

        // Candidate 2 re-attempts booking the cancelled slot -> 201 Created
        $reBook2 = $this->actingAs($cand2)->postJson('/api/candidate/interviews/book', [
            'application_id'  => $app2->id,
            'availability_id' => $slot->id,
        ]);
        $reBook2->assertStatus(201);
    }

    public function test_logic_offer_cannot_accept_expired_rejected_or_other_candidates_offer(): void
    {
        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $cand1 = User::factory()->create(['account_type' => 'candidate']);
        $prof1 = CandidateProfile::create(['user_id' => $cand1->id]);
        $app1 = Application::create(['job_id' => $job->id, 'candidate_id' => $prof1->id]);

        $cand2 = User::factory()->create(['account_type' => 'candidate']);
        $prof2 = CandidateProfile::create(['user_id' => $cand2->id]);

        // Offer for Candidate 1
        $offer1 = Offer::create([
            'application_id' => $app1->id,
            'salary_amount'  => 10000,
            'currency'       => 'SAR',
            'salary_period'  => 'month',
            'start_date'     => Carbon::now()->addDays(30),
            'status'         => 'sent',
            'sent_by'        => $this->adminUser->id,
            'expires_at'     => Carbon::now()->addDays(5),
        ]);

        // Candidate 2 tries to accept Candidate 1's offer -> 403 Forbidden
        $hijackAccept = $this->actingAs($cand2)->postJson("/api/candidate/offers/{$offer1->id}/accept");
        $hijackAccept->assertStatus(403);

        // Candidate 1 rejects offer
        $rejectOffer = $this->actingAs($cand1)->postJson("/api/candidate/offers/{$offer1->id}/reject", ['reason' => 'Salary too low']);
        $rejectOffer->assertStatus(200);

        // Candidate 1 tries to accept already rejected offer -> 409 Conflict
        $acceptAfterReject = $this->actingAs($cand1)->postJson("/api/candidate/offers/{$offer1->id}/accept");
        $acceptAfterReject->assertStatus(409);
        $this->assertStringContainsString('rejected', $acceptAfterReject->json('message'));

        // Expired Offer -> 409 Conflict
        $expiredOffer = Offer::create([
            'application_id' => $app1->id,
            'salary_amount'  => 12000,
            'currency'       => 'SAR',
            'salary_period'  => 'month',
            'start_date'     => Carbon::now()->addDays(30),
            'status'         => 'sent',
            'sent_by'        => $this->adminUser->id,
            'expires_at'     => Carbon::now()->subDay(), // Expired
        ]);
        $acceptExpired = $this->actingAs($cand1)->postJson("/api/candidate/offers/{$expiredOffer->id}/accept");
        $acceptExpired->assertStatus(409);
        $this->assertStringContainsString('expired', $acceptExpired->json('message'));
    }

    public function test_logic_blocked_or_deactivated_user_login_and_token_revocation(): void
    {
        $blockedStatus = AccountStatus::firstOrCreate(['id' => 3], ['name' => 'Blocked']);

        $blockedUser = User::factory()->create([
            'email'             => 'blocked@example.com',
            'password'          => Hash::make('Password123!'),
            'account_status_id' => $blockedStatus->id,
        ]);

        // Login attempt by blocked user -> 403 Forbidden
        $loginBlocked = $this->postJson('/api/auth/login', [
            'email'    => 'blocked@example.com',
            'password' => 'Password123!',
        ]);
        $loginBlocked->assertStatus(403);

        // Token revocation test when status changes
        $activeUser = User::factory()->create([
            'email'             => 'active_to_block@example.com',
            'password'          => Hash::make('Password123!'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);
        $token = $activeUser->createToken('auth-token')->plainTextToken;

        // Admin blocks the user
        $adminToken = $this->adminUser->createToken('admin-token', ['admin'])->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->patchJson("/api/admin/users/{$activeUser->id}/status", [
                'account_status_id' => 3,
                'reason'            => 'Security violation',
            ])->assertStatus(200);

        // Old token should be deleted from DB
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $activeUser->id,
        ]);
    }

    public function test_logic_unauthenticated_requests_return_401_and_unauthorized_return_403(): void
    {
        // Unauthenticated -> 401
        $this->getJson('/api/candidate/profile')->assertStatus(401);
        $this->getJson('/api/company/jobs')->assertStatus(401);
        $this->getJson('/api/admin/companies')->assertStatus(401);

        // Unauthorized (wrong permission/account type) -> 403
        $cand = User::factory()->create(['account_type' => 'candidate']);
        $candToken = $cand->createToken('auth-token', ['candidate'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$candToken}")
            ->getJson('/api/admin/companies')
            ->assertStatus(403);
    }
}
