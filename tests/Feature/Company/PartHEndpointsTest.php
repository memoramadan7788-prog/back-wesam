<?php

namespace Tests\Feature\Company;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartHEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_dashboard_and_job_publication_contract(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'status' => 'approved']);
        $job = Job::factory()->draft()->create(['company_id' => $company->id, 'title' => 'Backend Engineer']);

        $this->actingAs($owner)->getJson('/api/company/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.open_requisitions', 0);

        $this->actingAs($owner)->getJson("/api/company/jobs/{$job->id}/publication-checklist")
            ->assertOk()
            ->assertJsonPath('data.ready', true);

        $this->actingAs($owner)->postJson("/api/company/jobs/{$job->id}/publish")
            ->assertOk()
            ->assertJsonPath('job.status', 'published');
    }

    public function test_company_ats_filters_groups_and_protects_tenant_data(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $otherOwner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'status' => 'approved']);
        $otherCompany = Company::factory()->create(['owner_user_id' => $otherOwner->id, 'status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $otherJob = Job::factory()->create(['company_id' => $otherCompany->id]);
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id, 'headline' => 'Laravel developer']);
        $app = Application::create(['job_id' => $job->id, 'candidate_id' => $profile->id, 'status' => 'submitted', 'source' => 'referral', 'applied_at' => now()]);
        Application::create(['job_id' => $otherJob->id, 'candidate_id' => $profile->id, 'status' => 'submitted']);

        $this->actingAs($owner)->getJson("/api/company/jobs/{$job->id}/applications?source=referral&search=Laravel")
            ->assertOk()
            ->assertJsonPath('meta.stage_counts.submitted', 1)
            ->assertJsonPath('data.0.id', $app->id);

        $this->actingAs($owner)->getJson("/api/company/applications/{$app->id}/notes")
            ->assertOk();
        $this->actingAs($owner)->postJson("/api/company/applications/{$app->id}/notes", ['body' => 'Review portfolio'])
            ->assertCreated();

        $this->actingAs($owner)->getJson("/api/company/applications/".Application::where('job_id', $otherJob->id)->first()->id)
            ->assertForbidden();
    }

    public function test_company_hiring_team_workspace_and_bulk_operations_are_available(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $employee = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'status' => 'approved']);
        CompanyEmployee::create(['company_id' => $company->id, 'user_id' => $employee->id, 'status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $app = Application::create(['job_id' => $job->id, 'candidate_id' => $profile->id, 'status' => 'submitted']);

        $this->actingAs($owner)->postJson("/api/company/jobs/{$job->id}/hiring-team", ['user_id' => $employee->id, 'role' => 'hiring_manager'])
            ->assertCreated();
        $this->actingAs($owner)->getJson("/api/company/jobs/{$job->id}/overview")
            ->assertOk()
            ->assertJsonPath('data.stage_counts.submitted', 1);
        $this->actingAs($owner)->putJson('/api/company/workspace', ['display_name' => 'Acme Hiring', 'size' => '11-50', 'setup_completed' => true])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Acme Hiring');
        $this->actingAs($owner)->patchJson('/api/company/applications/bulk', ['application_ids' => [$app->id], 'owner_id' => $employee->id])
            ->assertOk()
            ->assertJsonPath('updated', 1);
    }
}
