<?php

namespace Tests\Feature\Interview;

use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewEvaluation;
use App\Models\InterviewerAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InterviewerAndEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function setUpDatabase(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->nullable();
            });
        }

        if (!Schema::hasTable('candidate_profiles')) {
            Schema::create('candidate_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('phone')->nullable();
                $table->string('headline')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('candidate_cvs')) {
            Schema::create('candidate_cvs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('candidate_id');
                $table->integer('version')->default(1);
                $table->string('storage_path');
                $table->string('original_filename');
                $table->string('mime_type')->default('application/pdf');
                $table->unsignedBigInteger('size_bytes')->default(102400);
                $table->boolean('is_active')->default(true);
                $table->dateTime('uploaded_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('candidate_id');
                $table->unsignedBigInteger('job_id')->nullable();
                $table->unsignedBigInteger('cv_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('interviews')) {
            Schema::create('interviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id');
                $table->integer('round_no')->default(1);
                $table->string('interview_type');
                $table->string('status');
                $table->string('location')->nullable();
                $table->string('meeting_link')->nullable();
                $table->text('instructions')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('interviewer_availability')) {
            Schema::create('interviewer_availability', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('interviewer_id');
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->string('availability_status')->default('open');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('interview_bookings')) {
            Schema::create('interview_bookings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('interview_id');
                $table->unsignedBigInteger('interviewer_id');
                $table->unsignedBigInteger('availability_id');
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->string('booking_status')->default('confirmed');
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('interview_evaluations')) {
            Schema::create('interview_evaluations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('interview_id');
                $table->unsignedBigInteger('evaluator_id');
                $table->decimal('numeric_score', 5, 2);
                $table->text('internal_notes');
                $table->text('candidate_feedback')->nullable();
                $table->dateTime('feedback_shared_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_interviewer_can_view_assigned_interviews(): void
    {
        $interviewer = User::factory()->create();
        $otherUser = User::factory()->create();

        $candidateUser = User::factory()->create(['name' => 'John Candidate']);
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->addDay();
        $end = $start->copy()->addMinutes(45);

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $assignedInterview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $assignedInterview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        // Unassigned interview
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $otherUser->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);
        $unassignedInterview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $unassignedInterview->id,
            'interviewer_id' => $otherUser->id,
            'availability_id' => $slot2->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($interviewer)
            ->getJson('/api/interviewer/interviews');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $assignedInterview->id);
        $response->assertJsonPath('data.0.candidate.name', 'John Candidate');
    }

    public function test_interviewer_can_view_assigned_interview_details_with_candidate_info_and_application_specific_cv(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create([
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
        ]);
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'phone' => '+1234567890',
            'headline' => 'Senior Backend Engineer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Candidate has two CVs in candidate_cvs
        $oldCvId = DB::table('candidate_cvs')->insertGetId([
            'candidate_id' => $profileId,
            'version' => 1,
            'storage_path' => 'private/cvs/alice_v1_secret.pdf',
            'original_filename' => 'Alice_Resume_v1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 54000,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appliedCvId = DB::table('candidate_cvs')->insertGetId([
            'candidate_id' => $profileId,
            'version' => 2,
            'storage_path' => 'private/cvs/alice_v2_secret.pdf',
            'original_filename' => 'Alice_Backend_Resume_2026.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 98000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Application specifically references appliedCvId via applications.cv_id
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'cv_id' => $appliedCvId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->addDay();
        $end = $start->copy()->addMinutes(45);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($interviewer)
            ->getJson("/api/interviewer/interviews/{$interview->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $interview->id);

        // Assert minimum required candidate information is exposed
        $response->assertJsonPath('data.candidate.name', 'Alice Smith');
        $response->assertJsonPath('data.candidate.email', 'alice@example.com');
        $response->assertJsonPath('data.candidate.phone', '+1234567890');
        $response->assertJsonPath('data.candidate.headline', 'Senior Backend Engineer');

        // Assert specific CV linked to applications.cv_id is selected (not old CV)
        $response->assertJsonPath('data.candidate.cv.cv_id', $appliedCvId);
        $response->assertJsonPath('data.candidate.cv.filename', 'Alice_Backend_Resume_2026.pdf');
        $response->assertJsonPath('data.candidate.cv.mime_type', 'application/pdf');

        // Assert internal raw storage_path is NEVER exposed in the JSON response
        $response->assertJsonMissing(['storage_path' => 'private/cvs/alice_v2_secret.pdf']);
    }

    public function test_unassigned_user_cannot_view_interview_details(): void
    {
        $interviewer = User::factory()->create();
        $unassignedUser = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->addDay();
        $end = $start->copy()->addMinutes(45);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($unassignedUser)
            ->getJson("/api/interviewer/interviews/{$interview->id}");

        $response->assertStatus(403);
    }

    public function test_interviewer_can_submit_evaluation_with_erd_fields(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->subHours(2);
        $end = $start->copy()->addMinutes(45);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $payload = [
            'numeric_score' => 88.5,
            'internal_notes' => 'Internal discussion: good architecture grasp, candidate fits backend team.',
            'candidate_feedback' => 'Clear communication on distributed caching.',
        ];

        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/evaluations", $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('interview_evaluations', [
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 88.5,
            'internal_notes' => 'Internal discussion: good architecture grasp, candidate fits backend team.',
            'feedback_shared_at' => null, // Remained null
        ]);
    }

    public function test_client_cannot_arbitrarily_set_feedback_shared_at_to_expose_feedback(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->subHours(2);
        $end = $start->copy()->addMinutes(45);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        // Attempt to pass client-controlled feedback_shared_at
        $payload = [
            'numeric_score' => 90.0,
            'internal_notes' => 'Private evaluation notes',
            'candidate_feedback' => 'Draft candidate feedback',
            'feedback_shared_at' => Carbon::now()->toIso8601String(), // Client tries to publish
        ];

        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/evaluations", $payload);

        $response->assertStatus(201);

        // Assert feedback_shared_at in database remained null despite client payload
        $this->assertDatabaseHas('interview_evaluations', [
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'feedback_shared_at' => null,
        ]);

        // Assert candidate cannot view the feedback
        $candidateResponse = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $candidateResponse->assertStatus(200);
        $candidateResponse->assertJsonMissing(['shared_feedback']);
    }

    public function test_candidate_cannot_submit_interview_evaluation(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->addDay();
        $end = $start->copy()->addMinutes(45);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $payload = [
            'numeric_score' => 100,
            'internal_notes' => 'Candidate trying to grade self',
        ];

        $response = $this->actingAs($candidateUser)
            ->postJson("/api/interviews/{$interview->id}/evaluations", $payload);

        $response->assertStatus(403);
    }

    public function test_cancelled_interview_cannot_be_evaluated(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'cancelled',
        ]);

        $payload = [
            'numeric_score' => 50,
            'internal_notes' => 'Interview was cancelled',
        ];

        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/evaluations", $payload);

        $response->assertStatus(403);
    }

    public function test_interviewer_sees_null_cv_when_application_has_no_cv_linked(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create(['name' => 'Bob NoCV']);
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'cv_id' => null, // No CV attached
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->addDay();
        $end = $start->copy()->addMinutes(45);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($interviewer)
            ->getJson("/api/interviewer/interviews/{$interview->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.candidate.name', 'Bob NoCV');
        $response->assertJsonPath('data.candidate.cv', null);
    }

    public function test_unauthenticated_user_cannot_access_interviewer_endpoints(): void
    {
        $response = $this->getJson('/api/interviewer/interviews');
        $response->assertStatus(401);

        $responseDetail = $this->getJson('/api/interviewer/interviews/1');
        $responseDetail->assertStatus(401);
    }

    public function test_authorized_assigned_interviewer_can_publish_eligible_evaluation(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'booking_status' => 'confirmed',
        ]);

        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 88.0,
            'internal_notes' => 'Internal discussion: good hire candidate.',
            'candidate_feedback' => 'Solid technical performance in algorithms.',
            'feedback_shared_at' => null,
        ]);

        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluation->id}/publish");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $evaluation->id);
        $response->assertJsonPath('data.interview_id', $interview->id);
        $response->assertJsonPath('data.is_published', true);
        $this->assertNotNull($response->json('data.feedback_shared_at'));

        $this->assertNotNull($evaluation->fresh()->feedback_shared_at);
        $this->assertDatabaseMissing('interview_evaluations', [
            'id' => $evaluation->id,
            'feedback_shared_at' => null,
        ]);
    }

    public function test_authorized_hr_admin_user_can_publish_eligible_evaluation(): void
    {
        $hrUser = User::factory()->create(['role' => 'hr']);
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'booking_status' => 'confirmed',
        ]);

        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 95.0,
            'internal_notes' => 'HR review pending',
            'candidate_feedback' => 'Exceptional system design expertise.',
            'feedback_shared_at' => null,
        ]);

        $response = $this->actingAs($hrUser)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluation->id}/publish");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_published', true);
    }

    public function test_unrelated_or_unassigned_user_cannot_publish_evaluation(): void
    {
        $assignedInterviewer = User::factory()->create();
        $unrelatedUser = User::factory()->create(['role' => null]);
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $assignedInterviewer->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $assignedInterviewer->id,
            'availability_id' => $slot->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'booking_status' => 'confirmed',
        ]);

        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $assignedInterviewer->id,
            'numeric_score' => 70.0,
            'internal_notes' => 'Confidential feedback',
            'candidate_feedback' => 'Needs improvement in unit testing.',
            'feedback_shared_at' => null,
        ]);

        // Unrelated user tries to publish
        $response = $this->actingAs($unrelatedUser)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluation->id}/publish");

        $response->assertStatus(403);

        // Candidate also cannot publish
        $candidateResponse = $this->actingAs($candidateUser)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluation->id}/publish");

        $candidateResponse->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_publish_evaluation(): void
    {
        $response = $this->postJson('/api/interviews/1/evaluations/1/publish');
        $response->assertStatus(401);
    }

    public function test_evaluation_belonging_to_different_interview_cannot_be_published_through_route(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview1 = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        $interview2 = Interview::create([
            'application_id' => $appId,
            'round_no' => 2,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);

        $evaluation2 = InterviewEvaluation::create([
            'interview_id' => $interview2->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 80.0,
            'internal_notes' => 'Evaluation for interview 2',
            'candidate_feedback' => 'Feedback for round 2',
            'feedback_shared_at' => null,
        ]);

        // Attempt to publish evaluation2 through interview1 URL
        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview1->id}/evaluations/{$evaluation2->id}/publish");

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'The specified evaluation does not belong to this interview.');
    }

    public function test_client_supplied_feedback_shared_at_cannot_manipulate_publication(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'booking_status' => 'confirmed',
        ]);

        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 85.0,
            'internal_notes' => 'Internal evaluation notes',
            'candidate_feedback' => 'Good performance',
            'feedback_shared_at' => null,
        ]);

        // Client attempts to supply a fake past timestamp
        $fakeTimestamp = '2019-01-01T00:00:00Z';
        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluation->id}/publish", [
                'feedback_shared_at' => $fakeTimestamp,
            ]);

        $response->assertStatus(200);
        $evaluation->refresh();

        // The stored timestamp must be server-generated (recent), not the client's fake timestamp
        $this->assertNotEquals($fakeTimestamp, $evaluation->feedback_shared_at->toIso8601String());
        $this->assertTrue($evaluation->feedback_shared_at->isToday());
    }

    public function test_unpublished_evaluation_does_not_expose_score_or_feedback_to_candidate(): void
    {
        $candidateUser = User::factory()->create();
        $interviewer = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);

        InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 88.5,
            'internal_notes' => 'Unpublished private discussion notes',
            'candidate_feedback' => 'Unpublished feedback draft',
            'feedback_shared_at' => null,
        ]);

        $response = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $response->assertStatus(200);
        $response->assertJsonMissing(['shared_feedback']);
        $response->assertJsonMissing(['numeric_score', 'internal_notes', 'Unpublished feedback draft']);
    }

    public function test_published_evaluation_exposes_score_feedback_and_publication_timestamp_to_candidate(): void
    {
        $candidateUser = User::factory()->create();
        $interviewer = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'booking_status' => 'confirmed',
        ]);

        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 91.5,
            'internal_notes' => 'Super secret internal discussion',
            'candidate_feedback' => 'Excellent communication and coding prowess.',
            'feedback_shared_at' => null,
        ]);

        // Publish via endpoint
        $publishResponse = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluation->id}/publish");
        $publishResponse->assertStatus(200);

        // Candidate requests details
        $candidateResponse = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $candidateResponse->assertStatus(200);
        $candidateResponse->assertJsonPath('data.shared_feedback.0.numeric_score', 91.5);
        $candidateResponse->assertJsonPath('data.shared_feedback.0.candidate_feedback', 'Excellent communication and coding prowess.');
        $this->assertNotNull($candidateResponse->json('data.shared_feedback.0.feedback_shared_at'));

        // Internal notes must remain strictly hidden
        $candidateResponse->assertJsonMissing(['internal_notes', 'Super secret internal discussion']);
    }

    public function test_publishing_already_published_evaluation_preserves_original_timestamp_idempotently(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'booking_status' => 'confirmed',
        ]);

        $pastTimestamp = Carbon::now()->subDays(5)->setMicrosecond(0);

        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 80.0,
            'internal_notes' => 'Existing evaluation',
            'candidate_feedback' => 'Good effort',
            'feedback_shared_at' => $pastTimestamp,
        ]);

        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluation->id}/publish");

        $response->assertStatus(200);
        $evaluation->refresh();

        // Must preserve the original timestamp and not overwrite it with now()
        $this->assertEquals($pastTimestamp->toIso8601String(), $evaluation->feedback_shared_at->toIso8601String());
    }

    public function test_multiple_evaluations_are_published_and_exposed_independently(): void
    {
        $candidateUser = User::factory()->create();
        $interviewerA = User::factory()->create();
        $interviewerB = User::factory()->create();

        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);

        // Panel interview with two interviewers
        $slotA = InterviewerAvailability::create([
            'interviewer_id' => $interviewerA->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewerA->id,
            'availability_id' => $slotA->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'booking_status' => 'confirmed',
        ]);

        $slotB = InterviewerAvailability::create([
            'interviewer_id' => $interviewerB->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewerB->id,
            'availability_id' => $slotB->id,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHours(1),
            'booking_status' => 'confirmed',
        ]);

        // Evaluation A: Published
        $evaluationA = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewerA->id,
            'numeric_score' => 90.0,
            'internal_notes' => 'Evaluation A internal note',
            'candidate_feedback' => 'Feedback A published',
            'feedback_shared_at' => null,
        ]);

        // Evaluation B: Unpublished
        InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewerB->id,
            'numeric_score' => 65.0,
            'internal_notes' => 'Evaluation B secret note',
            'candidate_feedback' => 'Feedback B unpublished draft',
            'feedback_shared_at' => null,
        ]);

        // Publish only Evaluation A
        $this->actingAs($interviewerA)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluationA->id}/publish")
            ->assertStatus(200);

        // Candidate requests details
        $candidateResponse = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $candidateResponse->assertStatus(200);
        $candidateResponse->assertJsonCount(1, 'data.shared_feedback');
        $candidateResponse->assertJsonPath('data.shared_feedback.0.id', $evaluationA->id);
        $candidateResponse->assertJsonPath('data.shared_feedback.0.candidate_feedback', 'Feedback A published');

        // Unpublished evaluation B must NOT be exposed
        $candidateResponse->assertJsonMissing(['Feedback B unpublished draft']);
        $candidateResponse->assertJsonMissing(['Evaluation B secret note']);
    }

    public function test_interviewer_can_view_interview_instructions_in_list_and_details(): void
    {
        $interviewer = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2)->setTime(10, 0),
            'end_at' => Carbon::now()->addDays(2)->setTime(11, 0),
            'availability_status' => 'booked',
        ]);

        $instructionsText = 'Focus questions on concurrency patterns and message queues.';

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'instructions' => $instructionsText,
        ]);

        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $slot->start_at,
            'end_at' => $slot->end_at,
            'booking_status' => 'confirmed',
        ]);

        // 1. Details endpoint (GET /api/interviewer/interviews/{id})
        $detailResponse = $this->actingAs($interviewer)->getJson("/api/interviewer/interviews/{$interview->id}");
        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonPath('data.instructions', $instructionsText);

        // 2. List endpoint (GET /api/interviewer/interviews)
        $listResponse = $this->actingAs($interviewer)->getJson('/api/interviewer/interviews');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('data.0.instructions', $instructionsText);
    }
}
