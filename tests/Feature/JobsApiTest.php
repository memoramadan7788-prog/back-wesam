<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Job;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_available_jobs_with_expected_structure(): void
    {
        Job::factory()->create();

        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id', 'title', 'description', 'employment_type', 'work_mode', 'location',
                    'salary' => ['min', 'max', 'currency', 'period'],
                    'experience_years', 'deadline', 'published_at',
                    'company' => ['id', 'name', 'industry', 'website', 'logo_path'],
                    'category' => ['id', 'name'],
                    'skills',
                ]],
                'links',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_hides_unavailable_jobs(): void
    {
        $visible = Job::factory()->create();
        Job::factory()->draft()->create();
        Job::factory()->closed()->create();
        Job::factory()->expired()->create();
        Job::factory()->privateVisibility()->create();
        Job::factory()->state(['status' => 'archived'])->create();
        Job::factory()->for(Company::factory()->state(['status' => 'pending']))->create();

        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_search_by_title_is_case_insensitive(): void
    {
        Job::factory()->create(['title' => 'Laravel Developer']);
        Job::factory()->create(['title' => 'Accountant']);

        $this->getJson('/api/jobs?title=laravel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laravel Developer');
    }

    public function test_search_by_company_name(): void
    {
        Job::factory()->for(Company::factory()->state(['name' => 'Acme Corp']))->create();
        Job::factory()->for(Company::factory()->state(['name' => 'Other Ltd']))->create();

        $this->getJson('/api/jobs?company_name=acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company.name', 'Acme Corp');
    }

    public function test_keyword_search_does_not_treat_percent_as_wildcard(): void
    {
        Job::factory()->create(['title' => 'Backend Developer']);

        $this->getJson('/api/jobs?q=' . urlencode('%'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_combined_filters(): void
    {
        $match = Job::factory()->create(['work_mode' => 'remote', 'location' => 'Cairo', 'salary_min' => 10000, 'salary_max' => 20000]);
        Job::factory()->create(['work_mode' => 'onsite', 'location' => 'Cairo']);
        Job::factory()->create(['work_mode' => 'remote', 'location' => 'Alexandria']);

        $this->getJson('/api/jobs?work_mode=remote&location=Cairo&salary_min=9000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_filter_by_skill(): void
    {
        $skill = Skill::factory()->create();
        $with = Job::factory()->create();
        $with->skills()->attach($skill->id, ['is_required' => 1]);
        Job::factory()->create();

        $this->getJson('/api/jobs?skills[]=' . $skill->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $with->id)
            ->assertJsonPath('data.0.skills.0.is_required', true);
    }

    public function test_sorting_by_title_asc_and_desc(): void
    {
        foreach (['B', 'A', 'C'] as $t) {
            Job::factory()->create(['title' => $t]);
        }

        $this->getJson('/api/jobs?sort_by=title&sort_dir=asc')
            ->assertJsonPath('data.*.title', ['A', 'B', 'C']);

        $this->getJson('/api/jobs?sort_by=title&sort_dir=desc')
            ->assertJsonPath('data.*.title', ['C', 'B', 'A']);
    }

    public function test_default_sort_is_latest_published_first(): void
    {
        Job::factory()->create(['title' => 'Old', 'published_at' => now()->subDays(5)]);
        Job::factory()->create(['title' => 'New', 'published_at' => now()->subDay()]);

        $this->getJson('/api/jobs')->assertJsonPath('data.*.title', ['New', 'Old']);
    }

    public function test_pagination_metadata(): void
    {
        Job::factory()->count(5)->create();

        $this->getJson('/api/jobs?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_invalid_parameters_return_422(): void
    {
        $this->getJson('/api/jobs?per_page=1000')->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->getJson('/api/jobs?page=0')->assertStatus(422)->assertJsonValidationErrors('page');
        $this->getJson('/api/jobs?sort_by=password')->assertStatus(422)->assertJsonValidationErrors('sort_by');
        $this->getJson('/api/jobs?sort_dir=sideways')->assertStatus(422)->assertJsonValidationErrors('sort_dir');
        $this->getJson('/api/jobs?work_mode=moon')->assertStatus(422)->assertJsonValidationErrors('work_mode');
        $this->getJson('/api/jobs?salary_min=5000&salary_max=1000')->assertStatus(422)->assertJsonValidationErrors('salary_max');
    }

    public function test_search_with_no_match_returns_empty_data(): void
    {
        Job::factory()->create();

        $this->getJson('/api/jobs?q=zzzzzz')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_show_returns_job_details_with_relations(): void
    {
        $skill = Skill::factory()->create();
        $job = Job::factory()->create();
        $job->skills()->attach($skill->id, ['is_required' => 1]);

        $this->getJson("/api/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.description', $job->description)
            ->assertJsonPath('data.is_accepting_applications', true)
            ->assertJsonPath('data.company.id', $job->company_id)
            ->assertJsonPath('data.skills.0.name', $skill->name);
    }

    public function test_show_returns_404_for_missing_job(): void
    {
        $this->getJson('/api/jobs/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Job not found.');
    }

    public function test_show_returns_404_for_unavailable_jobs(): void
    {
        foreach ([
            Job::factory()->draft()->create(),
            Job::factory()->closed()->create(),
            Job::factory()->expired()->create(),
            Job::factory()->privateVisibility()->create(),
        ] as $job) {
            $this->getJson("/api/jobs/{$job->id}")->assertNotFound();
        }
    }

    public function test_private_fields_are_not_exposed(): void
    {
        $job = Job::factory()->create();

        $paths = [
            'data.created_by', 'data.visibility', 'data.status', 'data.private_apply_mode',
            'data.company.contact_email', 'data.company.contact_phone', 'data.company.doc_path',
            'data.company.owner_user_id', 'data.company.rejection_reason', 'data.company.address',
        ];

        $response = $this->getJson("/api/jobs/{$job->id}")->assertOk();
        foreach ($paths as $path) {
            $response->assertJsonMissingPath($path);
        }

        $list = $this->getJson('/api/jobs')->assertOk();
        $list->assertJsonMissingPath('data.0.created_by');
        $list->assertJsonMissingPath('data.0.company.contact_email');
        $list->assertJsonMissingPath('data.0.company.doc_path');
    }

    public function test_listing_does_not_require_authentication(): void
    {
        $this->getJson('/api/jobs')->assertOk();
    }
}
