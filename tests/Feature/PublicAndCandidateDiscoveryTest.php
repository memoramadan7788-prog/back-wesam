<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAndCandidateDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_support_multiple_filters_facets_and_candidate_state(): void
    {
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $company = Company::factory()->create([
            'description' => 'A product company.',
            'culture' => 'Remote-first.',
            'benefits' => ['Health insurance'],
            'locations' => ['Cairo', 'Remote'],
        ]);
        $matching = Job::factory()->create([
            'company_id' => $company->id,
            'title' => 'Remote Laravel Engineer',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'requisition_code' => 'REQ-100',
            'hiring_model' => 'direct',
        ]);
        Job::factory()->create([
            'company_id' => $company->id,
            'employment_type' => 'contract',
            'work_mode' => 'onsite',
            'requisition_code' => 'REQ-101',
        ]);
        $profile->savedJobs()->attach($matching->id);
        Application::create([
            'job_id' => $matching->id,
            'candidate_id' => $profile->id,
            'status' => 'submitted',
            'applied_at' => now(),
        ]);

        $response = $this->actingAs($candidate)->getJson('/api/jobs?employment_type[]=full_time&work_mode[]=remote');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('data.0.requisition_code', 'REQ-100')
            ->assertJsonPath('data.0.hiring_model', 'direct')
            ->assertJsonPath('data.0.saved_by_me', true)
            ->assertJsonPath('data.0.applied_by_me', true)
            ->assertJsonPath('data.0.company.about.description', 'A product company.')
            ->assertJsonStructure(['facets' => ['employment_type', 'work_mode', 'category']]);

        $this->getJson("/api/jobs/{$matching->id}")
            ->assertOk()
            ->assertJsonPath('data.requisition_code', 'REQ-100')
            ->assertJsonPath('data.posted_at', $matching->published_at->toIso8601String());
    }

    public function test_public_company_pages_and_featured_home_content_are_available(): void
    {
        $company = Company::factory()->create([
            'description' => 'Public company description',
            'culture' => 'Collaborative culture',
            'benefits' => ['Flexible hours'],
            'locations' => ['Alexandria'],
        ]);
        $job = Job::factory()->create(['company_id' => $company->id, 'requisition_code' => 'REQ-200']);

        $this->getJson('/api/companies')
            ->assertOk()
            ->assertJsonPath('data.0.id', $company->id)
            ->assertJsonPath('data.0.open_roles_count', 1);

        $this->getJson("/api/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.description', 'Public company description')
            ->assertJsonPath('data.locations.0', 'Alexandria')
            ->assertJsonPath('data.hiring_team.0.role', 'owner');

        $this->getJson("/api/companies/{$company->id}/jobs")
            ->assertOk()
            ->assertJsonPath('data.0.id', $job->id);

        $this->getJson('/api/public/featured')
            ->assertOk()
            ->assertJsonPath('data.featured_companies.0.id', $company->id)
            ->assertJsonPath('data.featured_jobs.0.id', $job->id)
            ->assertJsonPath('data.counts.companies', 1)
            ->assertJsonPath('data.counts.jobs', 1);
    }

    public function test_candidate_can_manage_saved_searches_hide_recommendations_and_see_saved_job_updates(): void
    {
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $company = Company::factory()->create();
        $job = Job::factory()->create([
            'company_id' => $company->id,
            'work_mode' => 'remote',
            'requisition_code' => 'REQ-300',
        ]);

        $search = $this->actingAs($candidate)->postJson('/api/candidate/saved-searches', [
            'name' => 'Remote Laravel jobs',
            'filters' => ['work_mode' => ['remote'], 'q' => 'Laravel'],
            'weekly_alerts' => true,
        ])->assertCreated()->json('data.id');

        $this->actingAs($candidate)
            ->putJson("/api/candidate/saved-searches/{$search}", ['is_paused' => true])
            ->assertOk()
            ->assertJsonPath('data.is_paused', true);

        $this->actingAs($candidate)
            ->getJson('/api/candidate/saved-searches')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Remote Laravel jobs');

        $this->actingAs($candidate)
            ->postJson("/api/candidate/recommended-jobs/{$job->id}/hide", ['reason' => 'Not relevant'])
            ->assertCreated();

        $this->actingAs($candidate)
            ->getJson('/api/candidate/recommended-jobs?hide_irrelevant=true')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($candidate)
            ->postJson("/api/candidate/saved-jobs/{$job->id}")
            ->assertCreated();
        $job->update(['title' => 'Updated Remote Role', 'updated_at' => now()->addMinute()]);

        $this->actingAs($candidate)
            ->getJson('/api/candidate/saved-jobs?work_model=remote&sort=updated_desc')
            ->assertOk()
            ->assertJsonPath('data.0.updated_since_saved', true)
            ->assertJsonPath('data.0.updated_since_saved_reason', 'Job details changed after you saved it.');

        $this->actingAs($candidate)
            ->deleteJson("/api/candidate/saved-searches/{$search}")
            ->assertOk();
    }
}
