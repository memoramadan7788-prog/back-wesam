<?php

namespace Tests\Feature\Company;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Job;
use App\Models\JobInterviewRound;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Package3InterviewFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_rounds_create_list_and_cancel_interviews(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'status'        => 'approved',
        ]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $interviewer = User::factory()->create(['account_type' => 'company']);
        CompanyEmployee::create([
            'company_id'          => $company->id,
            'user_id'             => $interviewer->id,
            'requested_job_title' => 'Technical Interviewer',
            'status'              => 'approved',
            'role_id'             => Role::where('name', 'interviewer')->value('id'),
            'joined_at'           => now(),
        ]);

        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $profile->id,
            'status'       => 'shortlisted',
        ]);

        $roundResponse = $this->actingAs($owner)->postJson("/api/company/jobs/{$job->id}/interview-rounds", [
            'round_no'       => 1,
            'interview_type' => 'online',
            'duration_min'   => 45,
        ]);
        $roundResponse->assertStatus(201)->assertJsonPath('data.round_no', 1);
        $roundId = $roundResponse->json('data.id');

        $this->actingAs($owner)
            ->getJson("/api/company/jobs/{$job->id}/interview-rounds")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner)
            ->getJson("/api/company/jobs/{$job->id}/interview-rounds/{$roundId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $roundId);

        $this->actingAs($owner)
            ->putJson("/api/company/jobs/{$job->id}/interview-rounds/{$roundId}", [
                'duration_min' => 60,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.duration_min', 60);

        $roundTwo = JobInterviewRound::create([
            'job_id'         => $job->id,
            'round_no'       => 2,
            'interview_type' => 'online',
            'duration_min'   => 30,
        ]);

        $interviewResponse = $this->actingAs($owner)->postJson("/api/company/applications/{$application->id}/interviews", [
            'round_no'       => $roundTwo->round_no,
            'interview_type' => 'online',
            'interviewer_id' => $interviewer->id,
            'instructions'   => 'Prepare a short system design discussion.',
        ]);
        $interviewResponse->assertStatus(201);
        $interviewId = $interviewResponse->json('data.id');
        $interviewResponse->assertJsonPath('data.application_id', $application->id);
        $interviewResponse->assertJsonPath('data.status', 'pending');

        $this->actingAs($owner)
            ->getJson('/api/company/interviews')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner)
            ->postJson("/api/company/interviews/{$interviewId}/cancel", [
                'reason' => 'Hiring plan changed.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($owner)
            ->postJson("/api/company/interviews/{$interviewId}/cancel")
            ->assertStatus(409);

        $this->actingAs($owner)
            ->deleteJson("/api/company/jobs/{$job->id}/interview-rounds/{$roundId}")
            ->assertStatus(200);
        $this->assertSoftDeleted('job_interview_rounds', ['id' => $roundId]);
    }
}
