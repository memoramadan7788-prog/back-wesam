<?php

namespace Tests\Feature\Exam;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Job;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecureExamEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_create_exam_and_add_questions(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $examResponse = $this->actingAs($user)->postJson('/api/company/exams', [
            // company_id is auto-resolved from authenticated user (Phase 0.1)
            'title'          => 'PHP & Laravel Core Assessment',
            'time_limit_min' => 45,
            'passing_score'  => 70,
            'attempt_limit'  => 3,
        ]);

        $examResponse->assertStatus(201);
        $examResponse->assertJsonPath('exam.title', 'PHP & Laravel Core Assessment');
        $examId = $examResponse->json('exam.id');

        $questionResponse = $this->actingAs($user)->postJson("/api/company/exams/{$examId}/questions", [
            'question_type' => 'mcq',
            'content'       => 'What is the purpose of Eloquent in Laravel?',
            'points'        => 50,
            'options'       => [
                ['text' => 'ORM for database interactions', 'is_correct' => true],
                ['text' => 'A CSS framework', 'is_correct' => false],
                ['text' => 'A web server', 'is_correct' => false],
            ],
        ]);

        $questionResponse->assertStatus(201);
        $questionResponse->assertJsonPath('question.content', 'What is the purpose of Eloquent in Laravel?');
        $questionResponse->assertJsonCount(3, 'question.options');

        $this->assertDatabaseHas('questions', [
            'exam_id' => $examId,
            'points'  => 50,
        ]);
    }

    public function test_candidate_can_start_exam_and_options_hide_correct_flag(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create(['user_id' => $candidateUser->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'submitted',
        ]);

        $exam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $companyOwner->id,
            'title'          => 'Technical Screening',
            'time_limit_min' => 30,
            'passing_score'  => 50,
            'attempt_limit'  => 3,
            'status'         => 'published',
        ]);

        $question = Question::create([
            'exam_id'       => $exam->id,
            'question_type' => 'mcq',
            'content'       => 'What does PHP stand for?',
            'points'        => 50,
            'order_no'      => 1,
            'status'        => 'published',
        ]);

        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Hypertext Preprocessor', 'is_correct' => true]);
        QuestionOption::create(['question_id' => $question->id, 'option_text' => 'Personal Home Page', 'is_correct' => false]);

        $response = $this->actingAs($candidateUser)->postJson("/api/candidate/exams/{$exam->id}/applications/{$application->id}/start");

        $response->assertStatus(200);
        $response->assertJsonPath('attempt.attempt_no', 1);
        $response->assertJsonPath('attempt.status', 'in_progress');

        // Security check: ensure is_correct is NOT leaked to candidate
        $firstOption = $response->json('questions.0.options.0');
        $this->assertArrayNotHasKey('is_correct', $firstOption);
    }

    public function test_candidate_submits_exam_and_gets_auto_graded(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create(['user_id' => $candidateUser->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'submitted',
        ]);

        $exam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $companyOwner->id,
            'title'          => 'Math Test',
            'time_limit_min' => 20,
            'passing_score'  => 10,
            'attempt_limit'  => 3,
            'status'         => 'published',
        ]);

        $q1 = Question::create([
            'exam_id'       => $exam->id,
            'question_type' => 'mcq',
            'content'       => '2 + 2 = ?',
            'points'        => 10,
            'order_no'      => 1,
            'status'        => 'published',
        ]);

        $optCorrect = QuestionOption::create(['question_id' => $q1->id, 'option_text' => '4', 'is_correct' => true]);
        $optWrong = QuestionOption::create(['question_id' => $q1->id, 'option_text' => '5', 'is_correct' => false]);

        $attempt = ExamAttempt::create([
            'exam_id'        => $exam->id,
            'application_id' => $application->id,
            'attempt_no'     => 1,
            'status'         => 'in_progress',
            'started_at'     => now(),
        ]);

        $response = $this->actingAs($candidateUser)->postJson("/api/candidate/exam-attempts/{$attempt->id}/submit", [
            'answers' => [
                [
                    'question_id'        => $q1->id,
                    'selected_option_id' => $optCorrect->id,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('total_score', 10);
        $response->assertJsonPath('is_passed', true);

        $this->assertDatabaseHas('exam_attempts', [
            'id'     => $attempt->id,
            'status' => 'submitted',
        ]);

        $this->assertDatabaseHas('grading_results', [
            'attempt_id'  => $attempt->id,
            'total_score' => 10,
        ]);
    }

    public function test_attempt_limit_enforced_strictly(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create(['user_id' => $candidateUser->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'submitted',
        ]);

        $exam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $companyOwner->id,
            'title'          => 'Strict Limit Exam',
            'time_limit_min' => 15,
            'passing_score'  => 50,
            'attempt_limit'  => 2,
            'status'         => 'published',
        ]);

        // Attempt 1
        ExamAttempt::create([
            'exam_id'        => $exam->id,
            'application_id' => $application->id,
            'attempt_no'     => 1,
            'status'         => 'submitted',
            'started_at'     => now()->subHours(2),
            'submitted_at'   => now()->subHours(1),
        ]);

        // Attempt 2
        ExamAttempt::create([
            'exam_id'        => $exam->id,
            'application_id' => $application->id,
            'attempt_no'     => 2,
            'status'         => 'submitted',
            'started_at'     => now()->subMinutes(30),
            'submitted_at'   => now()->subMinutes(10),
        ]);

        // Attempt 3 should be rejected with 403
        $response = $this->actingAs($candidateUser)->postJson("/api/candidate/exams/{$exam->id}/applications/{$application->id}/start");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Maximum trial attempts (2 attempts) reached for this exam.');
    }

    public function test_company_can_attach_exam_with_default_or_explicit_stage_order(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $firstExam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $companyOwner->id,
            'title'          => 'First Exam',
            'time_limit_min' => 30,
            'passing_score'  => 70,
            'attempt_limit'  => 3,
            'status'         => 'published',
        ]);
        $secondExam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $companyOwner->id,
            'title'          => 'Second Exam',
            'time_limit_min' => 30,
            'passing_score'  => 70,
            'attempt_limit'  => 3,
            'status'         => 'published',
        ]);

        $this->actingAs($companyOwner)
            ->postJson("/api/company/jobs/{$job->id}/exams/{$firstExam->id}/attach")
            ->assertStatus(200)
            ->assertJsonPath('stage_order', 1);

        $this->actingAs($companyOwner)
            ->postJson("/api/company/jobs/{$job->id}/exams/{$secondExam->id}/attach")
            ->assertStatus(200)
            ->assertJsonPath('stage_order', 2);

        $this->assertDatabaseHas('job_exams', [
            'job_id'      => $job->id,
            'exam_id'     => $secondExam->id,
            'stage_order' => 2,
        ]);

        $this->actingAs($companyOwner)
            ->postJson("/api/company/jobs/{$job->id}/exams/{$secondExam->id}/attach", ['stage_order' => 7])
            ->assertStatus(200)
            ->assertJsonPath('stage_order', 7);
    }

    public function test_submitting_after_exam_time_limit_returns_conflict_and_expires_attempt(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create(['user_id' => $candidateUser->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'submitted',
        ]);
        $exam = Exam::create([
            'company_id'     => $company->id,
            'created_by'     => $companyOwner->id,
            'title'          => 'Timed Exam',
            'time_limit_min' => 30,
            'passing_score'  => 70,
            'attempt_limit'  => 3,
            'status'         => 'published',
        ]);
        $question = Question::create([
            'exam_id'       => $exam->id,
            'question_type' => 'mcq',
            'content'       => 'What is 2 + 2?',
            'points'        => 10,
            'order_no'      => 1,
            'status'        => 'published',
        ]);
        $option = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => '4',
            'is_correct'  => true,
        ]);
        $attempt = ExamAttempt::create([
            'exam_id'        => $exam->id,
            'application_id' => $application->id,
            'attempt_no'     => 1,
            'status'         => 'in_progress',
            'started_at'     => now()->subMinutes(31),
        ]);

        $response = $this->actingAs($candidateUser)->postJson("/api/candidate/exam-attempts/{$attempt->id}/submit", [
            'answers' => [[
                'question_id'        => $question->id,
                'selected_option_id' => $option->id,
            ]],
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('status', 'expired');
        $response->assertJsonPath('message', 'Exam time limit of 30 minutes has expired.');
        $this->assertDatabaseHas('exam_attempts', [
            'id'     => $attempt->id,
            'status' => 'expired',
        ]);
    }
}
