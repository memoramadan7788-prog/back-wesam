<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AdminPermission;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Interview;
use App\Models\Job;
use App\Models\Offer;
use App\Models\User;
use App\Models\AccountStatus;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRoleSeeder::class);

        $this->admin = Admin::create([
            'name' => 'Analytics Admin',
            'email' => 'analytics-admin@example.com',
            'password' => bcrypt('secret123'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);

        $this->admin->roles()->attach(
            AdminRole::where('name', 'super_admin')->value('id'),
            ['assigned_at' => now()]
        );
    }

    public function test_admin_can_view_detailed_analytics_for_users_companies_and_recruitment(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
        ]);
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $employee = User::factory()->create(['account_type' => 'company']);

        CompanyEmployee::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        $job = Job::factory()->create([
            'company_id' => $company->id,
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $application = Application::create([
            'job_id' => $job->id,
            'candidate_id' => $profile->id,
            'status' => 'hired',
            'applied_at' => now(),
        ]);
        Interview::create([
            'application_id' => $application->id,
            'status' => 'scheduled',
            'round_no' => 1,
            'interview_type' => 'online',
        ]);
        Offer::create([
            'application_id' => $application->id,
            'salary_amount' => 20000,
            'currency' => 'EGP',
            'salary_period' => 'month',
            'start_date' => now()->addMonth()->toDateString(),
            'status' => 'accepted',
            'sent_by' => $owner->id,
            'sent_at' => now(),
        ]);
        $exam = Exam::create([
            'company_id' => $company->id,
            'created_by' => $owner->id,
            'title' => 'Backend Assessment',
            'time_limit_min' => 30,
            'attempt_limit' => 3,
            'passing_score' => 70,
            'status' => 'published',
        ]);
        ExamAttempt::create([
            'exam_id' => $exam->id,
            'application_id' => $application->id,
            'attempt_no' => 1,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/analytics?months=3');

        $response->assertOk()
            ->assertJsonPath('data.period.months', 3)
            ->assertJsonPath('data.overview.companies.total', 1)
            ->assertJsonPath('data.overview.employees.approved', 1)
            ->assertJsonPath('data.overview.jobs.published', 1)
            ->assertJsonPath('data.overview.applications.hired', 1)
            ->assertJsonPath('data.overview.interviews.scheduled', 1)
            ->assertJsonPath('data.overview.offers.accepted', 1)
            ->assertJsonPath('data.overview.exams.published', 1)
            ->assertJsonPath('data.overview.exams.submitted_attempts', 1)
            ->assertJsonPath('data.recruitment_funnel.hire_rate', 100)
            ->assertJsonPath('data.top_companies.0.applications', 1)
            ->assertJsonPath('data.top_jobs.0.hired', 1)
            ->assertJsonCount(3, 'data.trends.monthly');
    }

    public function test_analytics_requires_reports_permission(): void
    {
        $role = AdminRole::create([
            'name' => 'limited_admin',
            'scope' => 'platform',
        ]);
        $admin = Admin::create([
            'name' => 'Limited Admin',
            'email' => 'limited-admin@example.com',
            'password' => bcrypt('secret123'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);
        $admin->roles()->attach($role->id, ['assigned_at' => now()]);

        $this->actingAs($admin)
            ->getJson('/api/admin/analytics')
            ->assertForbidden();
    }

    public function test_admin_with_reports_permission_but_without_super_admin_role_is_denied(): void
    {
        $role = AdminRole::create([
            'name' => 'reports_viewer',
            'scope' => 'platform',
        ]);
        $role->permissions()->attach(
            AdminPermission::where('key', 'reports.view')->value('id')
        );
        $admin = Admin::create([
            'name' => 'Reports Viewer',
            'email' => 'reports-viewer@example.com',
            'password' => bcrypt('secret123'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);
        $admin->roles()->attach($role->id, ['assigned_at' => now()]);

        $this->actingAs($admin)
            ->getJson('/api/admin/analytics')
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden. Super Admin access only.']);
    }

    public function test_platform_dashboard_is_reserved_for_super_admin(): void
    {
        $role = AdminRole::create([
            'name' => 'dashboard_viewer',
            'scope' => 'platform',
        ]);
        $role->permissions()->attach(
            AdminPermission::where('key', 'reports.view')->value('id')
        );
        $admin = Admin::create([
            'name' => 'Dashboard Viewer',
            'email' => 'dashboard-viewer@example.com',
            'password' => bcrypt('secret123'),
            'account_status_id' => AccountStatus::ACTIVE,
        ]);
        $admin->roles()->attach($role->id, ['assigned_at' => now()]);

        $this->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden. Super Admin access only.']);
    }

    public function test_super_admin_can_view_platform_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
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
