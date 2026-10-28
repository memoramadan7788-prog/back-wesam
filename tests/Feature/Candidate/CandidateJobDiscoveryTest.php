<?php

namespace Tests\Feature\Candidate;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateJobDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_view_empty_saved_jobs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/candidate/saved-jobs');
        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_candidate_can_save_and_unsave_job(): void
    {
        $user = User::factory()->create();
        $job = Job::factory()->create();

        // 1. Save job
        $saveResponse = $this->actingAs($user)->postJson("/api/candidate/saved-jobs/{$job->id}");
        $saveResponse->assertStatus(201)
            ->assertJsonPath('message', 'Job saved successfully.')
            ->assertJsonPath('job_id', $job->id);

        $this->assertDatabaseHas('candidate_saved_jobs', [
            'job_id' => $job->id,
        ]);

        // 2. Listing saved jobs
        $listResponse = $this->actingAs($user)->getJson('/api/candidate/saved-jobs');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $job->id);

        // 3. Duplicate save returns 200 already saved
        $dupResponse = $this->actingAs($user)->postJson("/api/candidate/saved-jobs/{$job->id}");
        $dupResponse->assertStatus(200)
            ->assertJsonPath('message', 'Job is already saved.');

        // 4. Unsave job
        $deleteResponse = $this->actingAs($user)->deleteJson("/api/candidate/saved-jobs/{$job->id}");
        $deleteResponse->assertStatus(200)
            ->assertJsonPath('message', 'Job removed from saved list successfully.');

        $this->assertDatabaseMissing('candidate_saved_jobs', [
            'job_id' => $job->id,
        ]);

        // 5. Unsaving non-saved job returns 404
        $deleteAgain = $this->actingAs($user)->deleteJson("/api/candidate/saved-jobs/{$job->id}");
        $deleteAgain->assertStatus(404);
    }

    public function test_saving_unavailable_or_nonexistent_job_returns_404(): void
    {
        $user = User::factory()->create();
        $draftJob = Job::factory()->draft()->create();

        $this->actingAs($user)
            ->postJson("/api/candidate/saved-jobs/{$draftJob->id}")
            ->assertStatus(404);

        $this->actingAs($user)
            ->postJson('/api/candidate/saved-jobs/99999')
            ->assertStatus(404);
    }

    public function test_candidate_can_get_recommended_jobs_ranked_by_matching_skills(): void
    {
        $skillPhp = Skill::factory()->create(['name' => 'PHP']);
        $skillLaravel = Skill::factory()->create(['name' => 'Laravel']);
        $skillReact = Skill::factory()->create(['name' => 'React']);

        $user = User::factory()->create();
        $profile = CandidateProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->skills()->attach([$skillPhp->id, $skillLaravel->id]);

        // Job 1 matches 2 skills
        $jobBest = Job::factory()->create(['title' => 'Senior Laravel Dev']);
        $jobBest->skills()->attach([$skillPhp->id, $skillLaravel->id]);

        // Job 2 matches 1 skill
        $jobMedium = Job::factory()->create(['title' => 'Junior PHP Dev']);
        $jobMedium->skills()->attach([$skillPhp->id]);

        // Job 3 matches 0 candidate skills
        $jobOther = Job::factory()->create(['title' => 'React Frontend']);
        $jobOther->skills()->attach([$skillReact->id]);

        $response = $this->actingAs($user)->getJson('/api/candidate/recommended-jobs');
        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');
        $this->assertEquals($jobBest->id, $data[0]['id']);
        $this->assertEquals($jobMedium->id, $data[1]['id']);
        $this->assertEquals($jobOther->id, $data[2]['id']);
    }

    public function test_recommended_jobs_excludes_already_applied_jobs(): void
    {
        $user = User::factory()->create();
        $profile = CandidateProfile::firstOrCreate(['user_id' => $user->id]);

        $jobApplied = Job::factory()->create(['title' => 'Applied Position']);
        $jobAvailable = Job::factory()->create(['title' => 'Available Position']);

        Application::create([
            'candidate_id' => $profile->id,
            'job_id'       => $jobApplied->id,
            'status'       => 'applied',
        ]);

        $response = $this->actingAs($user)->getJson('/api/candidate/recommended-jobs');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $jobAvailable->id);
    }

    public function test_candidate_can_view_and_sync_skills(): void
    {
        $user = User::factory()->create();
        $skill1 = Skill::factory()->create(['name' => 'PHP']);
        $skill2 = Skill::factory()->create(['name' => 'MySQL']);

        // Sync skills
        $syncResponse = $this->actingAs($user)->postJson('/api/candidate/skills', [
            'skills' => [
                ['skill_id' => $skill1->id, 'level' => 'expert'],
                ['skill_id' => $skill2->id, 'level' => 'intermediate'],
            ],
        ]);

        $syncResponse->assertOk()
            ->assertJsonCount(2, 'data');

        // View skills
        $listResponse = $this->actingAs($user)->getJson('/api/candidate/skills');
        $listResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'PHP')
            ->assertJsonPath('data.0.level', 'expert');
    }
}
