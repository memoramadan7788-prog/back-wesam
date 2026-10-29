<?php

namespace Tests\Feature\Application;

use App\Models\Application;
use App\Models\CandidateCv;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewerAvailability;
use App\Models\Job;
use App\Models\JobScreeningQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateApplicationFlowContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_read_screening_questions_and_submit_richer_application(): void
    {
        $candidate = User::factory()->create();
        $profile = CandidateProfile::create(['user_id' => $candidate->id, 'headline' => 'Backend Developer']);
        $companyOwner = User::factory()->create(['name' => 'Hiring Owner']);
        $company = Company::factory()->create([
            'owner_user_id' => $companyOwner->id,
            'status' => 'approved',
        ]);
        $job = Job::factory()->create([
            'company_id' => $company->id,
            'hiring_model' => 'direct',
            'requisition_code' => 'REQ-100',
        ]);
        $question = JobScreeningQuestion::create([
            'job_id' => $job->id,
            'question' => 'How many years of Laravel experience do you have?',
            'type' => 'text',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $cv = CandidateCv::create([
            'candidate_id' => $profile->id,
            'original_filename' => 'resume.pdf',
            'storage_path' => 'cvs/resume.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'version' => 1,
            'is_active' => true,
        ]);

        $this->getJson("/api/jobs/{$job->id}/screening-questions")
            ->assertOk()
            ->assertJsonPath('data.0.id', $question->id)
            ->assertJsonPath('data.0.is_required', true);

        $this->actingAs($candidate)->postJson("/api/jobs/{$job->id}/apply", [
            'cv_id' => $cv->id,
            'consent' => true,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Please answer all required screening questions.');

        $response = $this->actingAs($candidate)->postJson("/api/jobs/{$job->id}/apply", [
            'cv_id' => $cv->id,
            'answers' => [[
                'question_id' => $question->id,
                'answer' => 'Five years',
            ]],
            'expected_salary' => 25000,
            'portfolio_url' => 'https://example.test/portfolio',
            'keep_in_talent_pool' => true,
            'cover_note' => 'Please consider my application.',
            'consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('application.status', 'submitted')
            ->assertJsonPath('application.expected_salary', '25000.00')
            ->assertJsonPath('application.portfolio_url', 'https://example.test/portfolio')
            ->assertJsonPath('application.keep_in_talent_pool', true)
            ->assertJsonPath('application.job.hiring_model', 'direct')
            ->assertJsonPath('application.job.requisition_code', 'REQ-100')
            ->assertJsonPath('application.hiring_contacts.0.name', 'Hiring Owner');

        $this->assertDatabaseHas('applications', [
            'job_id' => $job->id,
            'candidate_id' => $profile->id,
            'expected_salary' => 25000,
            'portfolio_url' => 'https://example.test/portfolio',
            'keep_in_talent_pool' => 1,
        ]);
    }

    public function test_candidate_can_save_and_read_one_application_draft(): void
    {
        $candidate = User::factory()->create();
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $job = Job::factory()->create();
        $question = JobScreeningQuestion::create([
            'job_id' => $job->id,
            'question' => 'Are you available to start next month?',
            'type' => 'yes_no',
        ]);

        $payload = [
            'answers' => [['question_id' => $question->id, 'answer' => 'Yes']],
            'expected_salary' => 18000,
            'portfolio_url' => 'https://example.test/me',
            'keep_in_talent_pool' => false,
        ];

        $this->actingAs($candidate)
            ->putJson("/api/candidate/application-drafts/{$job->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.job_id', $job->id)
            ->assertJsonPath('data.answers.0.question_id', $question->id);

        $this->actingAs($candidate)
            ->getJson("/api/candidate/application-drafts/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.expected_salary', '18000.00');

        $this->assertDatabaseHas('candidate_application_drafts', [
            'candidate_id' => $profile->id,
            'job_id' => $job->id,
        ]);
    }

    public function test_application_details_and_activity_are_scoped_to_candidate(): void
    {
        $candidate = User::factory()->create();
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $application = Application::create([
            'job_id' => $job->id,
            'candidate_id' => $profile->id,
            'status' => 'submitted',
            'applied_at' => now(),
        ]);
        $interviewer = User::factory()->create();
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
            'booking_status' => 'booked',
        ]);

        $this->actingAs($candidate)
            ->getJson("/api/candidate/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.next_interview.id', $interview->id)
            ->assertJsonPath('data.submitted_documents.portfolio_url', null);

        $this->actingAs($candidate)
            ->getJson("/api/candidate/applications/{$application->id}/activity")
            ->assertOk()
            ->assertJsonStructure(['data' => [['type', 'occurred_at']]])
            ->assertJsonFragment(['type' => 'interview']);

        $otherCandidate = User::factory()->create();
        $this->actingAs($otherCandidate)
            ->getJson("/api/candidate/applications/{$application->id}")
            ->assertNotFound();
    }
}
