<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Exam;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobExam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PartAEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_compatibility_paths_are_registered(): void
    {
        $this->postJson('/api/auth/reset-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'token', 'password']);

        $this->postJson('/api/company/reset-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'token', 'password']);
    }

    public function test_password_change_is_shared_by_auth_company_and_candidate_paths(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user)
            ->putJson('/api/auth/change-password', [
                'current_password' => 'OldPassword123!',
                'new_password' => 'NewPassword123!',
                'new_password_confirmation' => 'NewPassword123!',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));

        $this->actingAs($user->fresh())
            ->putJson('/api/company/change-password', [
                'current_password' => 'NewPassword123!',
                'new_password' => 'NewestPassword123!',
                'new_password_confirmation' => 'NewestPassword123!',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('NewestPassword123!', $user->fresh()->password));
    }

    public function test_user_account_can_be_soft_deleted_through_company_alias(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/api/company/delete-account')
            ->assertOk()
            ->assertJson(['message' => 'Account deleted successfully.']);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_candidate_can_list_only_published_exams_for_applied_jobs(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
        ]);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);

        $application = Application::create([
            'job_id' => $job->id,
            'candidate_id' => $profile->id,
            'status' => 'submitted',
        ]);

        $exam = Exam::create([
            'company_id' => $company->id,
            'created_by' => $owner->id,
            'title' => 'Candidate-visible exam',
            'time_limit_min' => 30,
            'attempt_limit' => 2,
            'passing_score' => 70,
            'status' => 'published',
        ]);
        JobExam::create(['job_id' => $job->id, 'exam_id' => $exam->id, 'stage_order' => 1]);

        $draftExam = Exam::create([
            'company_id' => $company->id,
            'created_by' => $owner->id,
            'title' => 'Draft exam',
            'time_limit_min' => 30,
            'attempt_limit' => 2,
            'passing_score' => 70,
            'status' => 'draft',
        ]);
        JobExam::create(['job_id' => $job->id, 'exam_id' => $draftExam->id, 'stage_order' => 2]);

        $this->actingAs($candidate)
            ->getJson('/api/candidate/exams')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $exam->id)
            ->assertJsonPath('data.0.title', 'Candidate-visible exam');

        $this->assertDatabaseHas('applications', ['id' => $application->id]);
    }

    public function test_company_can_list_and_view_only_its_exams_with_questions(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
        ]);
        $otherOwner = User::factory()->create(['account_type' => 'company']);
        $otherCompany = Company::factory()->create([
            'owner_user_id' => $otherOwner->id,
            'status' => 'approved',
        ]);

        $exam = Exam::create([
            'company_id' => $company->id,
            'created_by' => $owner->id,
            'title' => 'Company exam',
            'time_limit_min' => 45,
            'attempt_limit' => 3,
            'passing_score' => 75,
            'status' => 'published',
        ]);
        $question = Question::create([
            'exam_id' => $exam->id,
            'question_type' => 'mcq',
            'content' => 'Company question',
            'points' => 10,
            'order_no' => 1,
            'status' => 'published',
        ]);
        QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Correct answer',
            'is_correct' => true,
        ]);
        $otherExam = Exam::create([
            'company_id' => $otherCompany->id,
            'created_by' => $otherOwner->id,
            'title' => 'Other company exam',
            'time_limit_min' => 30,
            'attempt_limit' => 2,
            'passing_score' => 60,
            'status' => 'published',
        ]);

        $this->actingAs($owner)
            ->getJson('/api/company/exams')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $exam->id)
            ->assertJsonPath('data.0.questions_count', 1);

        $this->actingAs($owner)
            ->getJson("/api/company/exams/{$exam->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $exam->id)
            ->assertJsonPath('data.questions.0.id', $question->id)
            ->assertJsonPath('data.questions.0.options.0.option_text', 'Correct answer');

        $this->actingAs($owner)
            ->getJson("/api/company/exams/{$otherExam->id}")
            ->assertNotFound();
    }

    public function test_company_can_list_interviews_for_one_owned_application_only(): void
    {
        $owner = User::factory()->create(['account_type' => 'company']);
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
        ]);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $application = Application::create([
            'job_id' => $job->id,
            'candidate_id' => $profile->id,
            'status' => 'interviewing',
        ]);
        $interview = Interview::create([
            'application_id' => $application->id,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'pending',
            'created_by' => $owner->id,
        ]);

        $otherOwner = User::factory()->create(['account_type' => 'company']);
        $otherCompany = Company::factory()->create([
            'owner_user_id' => $otherOwner->id,
            'status' => 'approved',
        ]);
        $otherJob = Job::factory()->create(['company_id' => $otherCompany->id]);
        $otherApplication = Application::create([
            'job_id' => $otherJob->id,
            'candidate_id' => $profile->id,
            'status' => 'interviewing',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/company/applications/{$application->id}/interviews")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $interview->id);

        $this->actingAs($owner)
            ->getJson("/api/company/applications/{$otherApplication->id}/interviews")
            ->assertForbidden();
    }
}
