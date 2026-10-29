<?php

namespace Tests\Feature\Candidate;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewerAvailability;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartCEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_part_c_candidate_endpoints_require_authentication(): void
    {
        $requests = [
            fn () => $this->getJson('/api/candidate/onboarding'),
            fn () => $this->putJson('/api/candidate/onboarding', ['steps' => []]),
            fn () => $this->getJson('/api/candidate/preferences'),
            fn () => $this->putJson('/api/candidate/preferences', []),
            fn () => $this->getJson('/api/candidate/notification-settings'),
            fn () => $this->putJson('/api/candidate/notification-settings', []),
            fn () => $this->getJson('/api/candidate/privacy'),
            fn () => $this->putJson('/api/candidate/privacy', []),
            fn () => $this->getJson('/api/candidate/profile/preview'),
            fn () => $this->getJson('/api/candidate/dashboard'),
        ];

        foreach ($requests as $request) {
            $request()->assertUnauthorized();
        }
    }

    public function test_candidate_can_persist_onboarding_and_job_preferences(): void
    {
        $user = User::factory()->create(['account_type' => 'candidate']);

        $this->actingAs($user)
            ->getJson('/api/candidate/onboarding')
            ->assertOk()
            ->assertJsonPath('data.completion_percent', 0);

        $this->actingAs($user)
            ->putJson('/api/candidate/onboarding', [
                'steps' => [
                    'basics' => ['completed' => true],
                    'resume_import' => ['completed' => true],
                    'skills_preferences' => ['completed' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.completion_percent', 100);

        $this->actingAs($user)
            ->putJson('/api/candidate/preferences', [
                'desired_titles' => ['Backend Engineer', 'Laravel Developer'],
                'location' => 'Cairo',
                'employment_type' => 'full_time',
                'min_salary' => 15000,
                'work_model' => 'hybrid',
                'open_to_relocation' => true,
                'include_agency' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.desired_titles.0', 'Backend Engineer')
            ->assertJsonPath('data.work_model', 'hybrid')
            ->assertJsonPath('data.open_to_relocation', true);

        $this->actingAs($user)
            ->getJson('/api/candidate/preferences')
            ->assertOk()
            ->assertJsonPath('data.min_salary', '15000.00');
    }

    public function test_candidate_can_update_notification_and_privacy_settings(): void
    {
        $user = User::factory()->create(['account_type' => 'candidate']);

        $this->actingAs($user)
            ->putJson('/api/candidate/notification-settings', [
                'weekly_digest' => true,
                'status_updates' => false,
                'interview_reminders' => true,
                'messages' => false,
                'marketing' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.weekly_digest', true)
            ->assertJsonPath('data.status_updates', false);

        $this->actingAs($user)
            ->putJson('/api/candidate/privacy', [
                'visibility' => 'public',
                'allow_agency_recruiters' => true,
                'show_in_public_search' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.visibility', 'public')
            ->assertJsonPath('data.show_in_public_search', true);

        $this->assertDatabaseHas('candidate_privacy_settings', [
            'candidate_id' => CandidateProfile::where('user_id', $user->id)->value('id'),
            'visibility' => 'public',
        ]);
    }

    public function test_candidate_can_update_owned_experience_and_education_only(): void
    {
        $user = User::factory()->create(['account_type' => 'candidate']);
        $otherUser = User::factory()->create(['account_type' => 'candidate']);

        $experienceId = $this->actingAs($user)->postJson('/api/candidate/experiences', [
            'company_name' => 'Old Company',
            'job_title' => 'Developer',
            'start_date' => '2022-01-01',
        ])->json('experience.id');

        $educationId = $this->actingAs($user)->postJson('/api/candidate/educations', [
            'institution' => 'Old University',
            'degree' => 'BSc',
            'graduation_year' => 2020,
        ])->json('education.id');

        $this->actingAs($user)
            ->putJson("/api/candidate/experiences/{$experienceId}", [
                'company_name' => 'New Company',
                'job_title' => 'Senior Developer',
            ])
            ->assertOk()
            ->assertJsonPath('experience.company_name', 'New Company');

        $this->actingAs($user)
            ->putJson("/api/candidate/educations/{$educationId}", [
                'institution' => 'New University',
            ])
            ->assertOk()
            ->assertJsonPath('education.institution', 'New University');

        $this->actingAs($otherUser)
            ->putJson("/api/candidate/experiences/{$experienceId}", ['company_name' => 'Not Allowed'])
            ->assertNotFound();

        $this->actingAs($otherUser)
            ->putJson("/api/candidate/educations/{$educationId}", ['institution' => 'Not Allowed'])
            ->assertNotFound();
    }

    public function test_candidate_can_view_an_employer_safe_profile_preview(): void
    {
        $user = User::factory()->create([
            'account_type' => 'candidate',
            'email' => 'private@example.com',
        ]);

        $this->actingAs($user)->putJson('/api/candidate/profile', [
            'headline' => 'Senior Laravel Engineer',
            'bio' => 'Public professional biography.',
            'phone' => '+201000000000',
            'location' => 'Cairo',
            'years_experience' => 6,
        ])->assertOk();

        $this->actingAs($user)
            ->getJson('/api/candidate/profile/preview')
            ->assertOk()
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.headline', 'Senior Laravel Engineer')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.phone');
    }

    public function test_candidate_dashboard_returns_active_applications_next_interview_and_actions(): void
    {
        $user = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $user->id, 'headline' => 'Engineer']);
        $company = Company::factory()->create();
        $job = Job::factory()->create(['company_id' => $company->id]);
        $application = Application::create([
            'job_id' => $job->id,
            'candidate_id' => $profile->id,
            'status' => 'interviewing',
            'applied_at' => now()->subDay(),
        ]);
        $interviewer = User::factory()->create(['account_type' => 'company']);
        $availability = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHour(),
            'availability_status' => 'booked',
        ]);
        $interview = Interview::create([
            'application_id' => $application->id,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $availability->id,
            'start_at' => $availability->start_at,
            'end_at' => $availability->end_at,
            'booking_status' => 'confirmed',
        ]);

        $this->actingAs($user)
            ->getJson('/api/candidate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_applications.0.id', $application->id)
            ->assertJsonPath('data.next_interview.id', $interview->id)
            ->assertJsonPath('data.profile_completion_percent', 0)
            ->assertJsonPath('data.next_actions.0.key', 'complete_onboarding');
    }
}
