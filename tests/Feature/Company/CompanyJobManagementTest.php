<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyJobManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_list_jobs(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        Job::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson('/api/company/jobs');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_company_can_create_job_with_skills(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $skill1 = Skill::create(['name' => 'Laravel']);
        $skill2 = Skill::create(['name' => 'Vue.js']);

        $payload = [
            // company_id is auto-resolved from authenticated user (Phase 0.1)
            'title'            => 'Senior Fullstack Engineer',
            'description'      => 'We are looking for an experienced full stack developer who loves clean code and robust systems.',
            'location'         => 'Cairo, Egypt',
            'work_mode'        => 'hybrid',
            'employment_type'  => 'full_time',
            'salary_min'       => 20000,
            'salary_max'       => 35000,
            'currency'         => 'EGP',
            'salary_period'    => 'month',
            'experience_years' => 4,
            'department'       => 'Engineering',
            'openings_count'   => 2,
            'deadline'         => now()->addDays(30)->toDateString(),
            'visibility'       => 'public',
            'skills'           => [$skill1->id, $skill2->id],
        ];

        $response = $this->actingAs($user)->postJson('/api/company/jobs', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('job.title', 'Senior Fullstack Engineer');
        $response->assertJsonPath('job.status', 'published');
        $response->assertJsonCount(2, 'job.skills');

        $this->assertDatabaseHas('jobs', [
            'company_id' => $company->id,
            'title'      => 'Senior Fullstack Engineer',
            'work_mode'  => 'hybrid',
            'status'     => 'published',
        ]);
    }

    public function test_job_creation_uses_only_canonical_store_job_field_names(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['owner_user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/company/jobs', [
            'title'            => 'Legacy Payload Job',
            'description'      => 'This payload intentionally uses legacy field aliases and must be rejected.',
            'job_category_id'  => 1,
            'workplace_type'   => 'remote',
            'min_salary'       => 1000,
            'max_salary'       => 2000,
            'city'             => 'Cairo',
            'country_id'       => 1,
            'employment_type'  => 'full_time',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'job_category_id',
            'workplace_type',
            'min_salary',
            'max_salary',
            'city',
            'country_id',
            'work_mode',
        ]);
        $this->assertDatabaseMissing('jobs', ['title' => 'Legacy Payload Job']);
    }

    public function test_company_can_view_single_job(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $job = Job::factory()->create(['company_id' => $company->id, 'title' => 'DevOps Architect']);

        $response = $this->actingAs($user)->getJson("/api/company/jobs/{$job->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'DevOps Architect');
        $response->assertJsonPath('data.company_id', $company->id);
    }

    public function test_company_can_update_job(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $job = Job::factory()->create(['company_id' => $company->id, 'title' => 'Junior Developer']);

        $response = $this->actingAs($user)->putJson("/api/company/jobs/{$job->id}", [
            'title'           => 'Mid-level Developer',
            'description'     => 'Updated description for this open mid-level developer role.',
            'work_mode'       => 'remote',
            'employment_type' => 'full_time',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('job.title', 'Mid-level Developer');

        $this->assertDatabaseHas('jobs', [
            'id'        => $job->id,
            'title'     => 'Mid-level Developer',
            'work_mode' => 'remote',
        ]);
    }

    public function test_company_can_change_job_status(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $job = Job::factory()->create(['company_id' => $company->id, 'status' => 'published']);

        $response = $this->actingAs($user)->patchJson("/api/company/jobs/{$job->id}/status", [
            'status' => 'closed',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('job.status', 'closed');

        $this->assertDatabaseHas('jobs', [
            'id'     => $job->id,
            'status' => 'closed',
        ]);
        $this->assertNotNull($job->fresh()->closed_at);
    }

    public function test_company_can_delete_job(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->deleteJson("/api/company/jobs/{$job->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('jobs', ['id' => $job->id]);
    }

    public function test_private_job_is_visible_only_to_approved_members_of_its_company(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
        ]);

        $employee = User::factory()->create(['account_type' => 'company']);
        CompanyEmployee::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        $otherOwner = User::factory()->create(['account_type' => 'company']);
        $otherCompany = Company::factory()->create([
            'owner_user_id' => $otherOwner->id,
            'status' => 'approved',
        ]);
        $otherEmployee = User::factory()->create(['account_type' => 'company']);
        CompanyEmployee::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherEmployee->id,
            'status' => 'approved',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($owner)->postJson('/api/company/jobs', [
            'title' => 'Internal Backend Opening',
            'description' => 'This internal opportunity is available only to employees of this company.',
            'work_mode' => 'hybrid',
            'employment_type' => 'full_time',
            'visibility' => 'private',
            'private_apply_mode' => 'all_employees',
        ]);

        $response->assertCreated();
        $job = Job::where('title', 'Internal Backend Opening')->firstOrFail();
        $this->assertSame('private', $job->visibility);
        $this->assertSame('all_employees', $job->private_apply_mode);

        $this->actingAs($employee)
            ->getJson('/api/company/internal-jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Internal Backend Opening');

        $this->actingAs($otherEmployee)
            ->getJson('/api/company/internal-jobs')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Internal Backend Opening']);

        $this->getJson("/api/jobs/{$job->id}")->assertNotFound();
    }

    public function test_user_without_company_cannot_view_internal_jobs(): void
    {
        $user = User::factory()->create(['account_type' => 'company']);

        $this->actingAs($user)
            ->getJson('/api/company/internal-jobs')
            ->assertForbidden();
    }
}
