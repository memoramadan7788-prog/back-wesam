<?php

namespace Tests\Feature\Candidate;

use App\Models\Category;
use App\Models\Company;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllJobDiscoveryEndpointsPostmanSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_postman_flow_for_all_job_discovery_apis(): void
    {
        // -------------------------------------------------------------
        // SETUP: Seed test database with Category, Skills, Company, and Jobs
        // -------------------------------------------------------------
        $category = Category::factory()->create(['name' => 'Software Engineering']);
        $company = Company::factory()->create([
            'name'   => 'Tech Corp',
            'status' => 'approved',
        ]);

        $skillPhp = Skill::factory()->create(['name' => 'PHP']);
        $skillLaravel = Skill::factory()->create(['name' => 'Laravel']);

        // Create published jobs
        $job1 = Job::factory()->create([
            'company_id'       => $company->id,
            'category_id'      => $category->id,
            'title'            => 'Senior Laravel Engineer',
            'location'         => 'Cairo',
            'work_mode'        => 'remote',
            'employment_type'  => 'full_time',
            'status'           => 'published',
            'visibility'       => 'public',
            'salary_min'       => 20000,
            'salary_max'       => 35000,
            'published_at'     => now()->subDays(2),
        ]);
        $job1->skills()->attach([$skillPhp->id, $skillLaravel->id]);

        $job2 = Job::factory()->create([
            'company_id'       => $company->id,
            'category_id'      => $category->id,
            'title'            => 'Junior Web Developer',
            'location'         => 'Alexandria',
            'work_mode'        => 'onsite',
            'employment_type'  => 'full_time',
            'status'           => 'published',
            'visibility'       => 'public',
            'published_at'     => now()->subDay(),
        ]);
        $job2->skills()->attach([$skillPhp->id]);

        // -------------------------------------------------------------
        // AUTHENTICATION: Candidate Login & Token Generation
        // -------------------------------------------------------------
        $candidateUser = User::factory()->create([
            'email'        => 'candidate.test@example.com',
            'password'     => bcrypt('Secret123!'),
            'account_type' => 'candidate',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'candidate.test@example.com',
            'password' => 'Secret123!',
        ]);
        $loginResponse->assertOk();
        $token = $loginResponse->json('access_token');
        $this->assertNotEmpty($token, 'Candidate Bearer token was received.');

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ];

        // =============================================================
        // [10] GET /api/jobs (Public Listing with Filters)
        // =============================================================
        $res10 = $this->getJson('/api/jobs');
        $res10->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'location', 'work_mode', 'salary',
                        'company' => ['id', 'name'],
                        'skills',
                    ]
                ],
                'links',
                'meta',
            ]);

        // Test with filter
        $res10Filter = $this->getJson('/api/jobs?work_mode=remote');
        $res10Filter->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $job1->id);

        // =============================================================
        // [11] GET /api/jobs/{id} (Public Single Job Detail)
        // =============================================================
        $res11 = $this->getJson("/api/jobs/{$job1->id}");
        $res11->assertOk()
            ->assertJsonPath('data.id', $job1->id)
            ->assertJsonPath('data.title', 'Senior Laravel Engineer')
            ->assertJsonPath('data.company.name', 'Tech Corp');

        // Verify non-existent returns 404
        $this->getJson('/api/jobs/99999')->assertStatus(404);

        // =============================================================
        // [BONUS] POST /api/candidate/skills (Add Candidate Skills)
        // =============================================================
        $resSkills = $this->withHeaders($headers)->postJson('/api/candidate/skills', [
            'skills' => [
                ['skill_id' => $skillPhp->id, 'level' => 'expert'],
                ['skill_id' => $skillLaravel->id, 'level' => 'expert'],
            ],
        ]);
        $resSkills->assertOk()->assertJsonCount(2, 'data');

        // =============================================================
        // [12] GET /api/candidate/recommended-jobs (Candidate Recommendations)
        // =============================================================
        $res12 = $this->withHeaders($headers)->getJson('/api/candidate/recommended-jobs');
        $res12->assertOk()
            ->assertJsonCount(2, 'data');
        // Job 1 has 2 matching skills, so it must be ranked #1
        $this->assertEquals($job1->id, $res12->json('data.0.id'));
        $this->assertEquals($job2->id, $res12->json('data.1.id'));

        // =============================================================
        // [13] GET /api/candidate/saved-jobs (Saved Jobs Listing - Empty Initially)
        // =============================================================
        $res13Initial = $this->withHeaders($headers)->getJson('/api/candidate/saved-jobs');
        $res13Initial->assertOk()->assertJsonCount(0, 'data');

        // =============================================================
        // [14] POST /api/candidate/saved-jobs/{jobId} (Save Job)
        // =============================================================
        $res14 = $this->withHeaders($headers)->postJson("/api/candidate/saved-jobs/{$job1->id}");
        $res14->assertStatus(201)
            ->assertJsonPath('message', 'Job saved successfully.')
            ->assertJsonPath('job_id', $job1->id);

        // Verify it appears in [13] saved list
        $res13AfterSave = $this->withHeaders($headers)->getJson('/api/candidate/saved-jobs');
        $res13AfterSave->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $job1->id)
            ->assertJsonPath('data.0.title', 'Senior Laravel Engineer');

        // Saving again returns 200 already saved (idempotent)
        $res14Duplicate = $this->withHeaders($headers)->postJson("/api/candidate/saved-jobs/{$job1->id}");
        $res14Duplicate->assertStatus(200)
            ->assertJsonPath('message', 'Job is already saved.');

        // =============================================================
        // [15] DELETE /api/candidate/saved-jobs/{jobId} (Unsave Job)
        // =============================================================
        $res15 = $this->withHeaders($headers)->deleteJson("/api/candidate/saved-jobs/{$job1->id}");
        $res15->assertStatus(200)
            ->assertJsonPath('message', 'Job removed from saved list successfully.');

        // Verify it was removed from [13] saved list
        $res13AfterDelete = $this->withHeaders($headers)->getJson('/api/candidate/saved-jobs');
        $res13AfterDelete->assertOk()->assertJsonCount(0, 'data');

        // Deleting non-saved returns 404
        $this->withHeaders($headers)->deleteJson("/api/candidate/saved-jobs/{$job1->id}")
            ->assertStatus(404);
    }
}
