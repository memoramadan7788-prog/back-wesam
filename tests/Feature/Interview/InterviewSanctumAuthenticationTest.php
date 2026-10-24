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

class InterviewSanctumAuthenticationTest extends TestCase
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

    public function test_unauthenticated_request_to_interview_endpoints_is_rejected_with_401(): void
    {
        // 1. Candidate endpoint without Bearer token
        $response1 = $this->getJson('/api/candidate/interviews');
        $response1->assertStatus(401);

        // 2. Interviewer endpoint without Bearer token
        $response2 = $this->getJson('/api/interviewer/interviews');
        $response2->assertStatus(401);

        // 3. Scheduling endpoint without Bearer token
        $response3 = $this->postJson('/api/interviews', [
            'application_id' => 1,
            'round_no' => 1,
            'interview_type' => 'online',
        ]);
        $response3->assertStatus(401);
    }

    public function test_valid_bearer_token_authenticates_candidate_on_candidate_endpoints(): void
    {
        $candidateUser = User::factory()->create(['name' => 'Sara Candidate', 'email' => 'sara@example.com']);
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'phone' => '+123456789',
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
            'status' => 'scheduled',
        ]);

        $token = $candidateUser->createToken('candidate-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidate/interviews');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'round_no', 'interview_type', 'status'],
            ],
        ]);
        $this->assertEquals($interview->id, $response->json('data.0.id'));
    }

    public function test_invalid_bearer_token_is_rejected_with_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-string-12345')
            ->getJson('/api/candidate/interviews');

        $response->assertStatus(401);
    }

    public function test_revoked_or_deleted_bearer_token_is_rejected_with_401(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Revoke token by deleting from personal_access_tokens before request
        $user->tokens()->delete();

        // Verify revoked token is rejected
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/candidate/interviews');
        $response->assertStatus(401);
    }

    public function test_candidate_with_valid_bearer_token_cannot_schedule_interview(): void
    {
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

        $interviewer = User::factory()->create();
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHour(),
            'availability_status' => 'open',
        ]);

        $token = $candidateUser->createToken('candidate-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/interviews', [
                'application_id' => $appId,
                'round_no' => 1,
                'interview_type' => 'online',
                'bookings' => [
                    ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_authorized_scheduler_with_valid_bearer_token_can_schedule_interview(): void
    {
        $schedulerUser = User::factory()->create(['role' => 'recruiter']);
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

        $interviewer = User::factory()->create();
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2)->setTime(10, 0),
            'end_at' => Carbon::now()->addDays(2)->setTime(11, 0),
            'availability_status' => 'open',
        ]);

        $token = $schedulerUser->createToken('scheduler-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/interviews', [
                'application_id' => $appId,
                'round_no' => 1,
                'interview_type' => 'online',
                'bookings' => [
                    ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('interviews', [
            'application_id' => $appId,
            'status' => 'scheduled',
            'created_by' => $schedulerUser->id,
        ]);
    }

    public function test_candidate_bearer_token_cannot_access_other_candidates_interview_details_idor(): void
    {
        $candidate1 = User::factory()->create();
        $profile1 = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidate1->id, 'created_at' => now(), 'updated_at' => now()]);
        $app1 = DB::table('applications')->insertGetId(['candidate_id' => $profile1, 'created_at' => now(), 'updated_at' => now()]);
        $interview1 = Interview::create([
            'application_id' => $app1,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $candidate2 = User::factory()->create();
        $profile2 = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidate2->id, 'created_at' => now(), 'updated_at' => now()]);
        $app2 = DB::table('applications')->insertGetId(['candidate_id' => $profile2, 'created_at' => now(), 'updated_at' => now()]);
        $interview2 = Interview::create([
            'application_id' => $app2,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $token1 = $candidate1->createToken('candidate-token-1')->plainTextToken;

        // Candidate 1 accessing Candidate 1's interview -> 200 OK
        $responseAllowed = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson("/api/candidate/interviews/{$interview1->id}");
        $responseAllowed->assertStatus(200);

        // Reset guard cache before second request
        $this->app['auth']->forgetGuards();

        // Candidate 1 attempting to access Candidate 2's interview -> 403 Forbidden
        $responseForbidden = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson("/api/candidate/interviews/{$interview2->id}");
        $responseForbidden->assertStatus(403);
    }

    public function test_interviewer_bearer_token_can_access_assigned_interview_and_denied_unassigned(): void
    {
        $interviewer1 = User::factory()->create();
        $interviewer2 = User::factory()->create();

        $candidate = User::factory()->create();
        $profile = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidate->id, 'created_at' => now(), 'updated_at' => now()]);
        $app = DB::table('applications')->insertGetId(['candidate_id' => $profile, 'created_at' => now(), 'updated_at' => now()]);

        $interview = Interview::create([
            'application_id' => $app,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer1->id,
            'start_at' => Carbon::now()->addDay(),
            'end_at' => Carbon::now()->addDay()->addHour(),
            'availability_status' => 'booked',
        ]);

        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer1->id,
            'availability_id' => $slot->id,
            'start_at' => $slot->start_at,
            'end_at' => $slot->end_at,
            'booking_status' => 'confirmed',
        ]);

        $token1 = $interviewer1->createToken('interviewer-token-1')->plainTextToken;
        $token2 = $interviewer2->createToken('interviewer-token-2')->plainTextToken;

        // Interviewer 1 (assigned) accessing interview details -> 200 OK
        $response1 = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson("/api/interviewer/interviews/{$interview->id}");
        $response1->assertStatus(200);

        // Reset auth guards to ensure fresh token authentication
        $this->app['auth']->forgetGuards();

        // Interviewer 2 (unassigned) accessing interview details -> 403 Forbidden
        $response2 = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson("/api/interviewer/interviews/{$interview->id}");
        $response2->assertStatus(403);
    }

    public function test_interviewer_bearer_token_can_submit_evaluation_for_assigned_interview_and_denied_unassigned(): void
    {
        $interviewer1 = User::factory()->create();
        $interviewer2 = User::factory()->create();

        $candidate = User::factory()->create();
        $profile = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidate->id, 'created_at' => now(), 'updated_at' => now()]);
        $app = DB::table('applications')->insertGetId(['candidate_id' => $profile, 'created_at' => now(), 'updated_at' => now()]);

        $interview = Interview::create([
            'application_id' => $app,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer1->id,
            'start_at' => Carbon::now()->addDay(),
            'end_at' => Carbon::now()->addDay()->addHour(),
            'availability_status' => 'booked',
        ]);

        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer1->id,
            'availability_id' => $slot->id,
            'start_at' => $slot->start_at,
            'end_at' => $slot->end_at,
            'booking_status' => 'confirmed',
        ]);

        $token1 = $interviewer1->createToken('interviewer-token-1')->plainTextToken;
        $token2 = $interviewer2->createToken('interviewer-token-2')->plainTextToken;

        // Unassigned interviewer submitting evaluation -> 403 Forbidden
        $responseForbidden = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson("/api/interviews/{$interview->id}/evaluations", [
                'numeric_score' => 85.5,
                'internal_notes' => 'Unauthorized evaluation attempt',
            ]);
        $responseForbidden->assertStatus(403);

        // Reset auth guards to ensure fresh token authentication
        $this->app['auth']->forgetGuards();

        // Assigned interviewer submitting evaluation -> 201 Created
        $responseAllowed = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson("/api/interviews/{$interview->id}/evaluations", [
                'numeric_score' => 92.0,
                'internal_notes' => 'Excellent problem solving skills.',
                'candidate_feedback' => 'Strong technical competency.',
            ]);
        $responseAllowed->assertStatus(201);
        $this->assertDatabaseHas('interview_evaluations', [
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer1->id,
            'numeric_score' => 92.0,
        ]);
    }

    public function test_admin_bearer_token_authenticates_via_sanctum_and_is_authorized_for_management(): void
    {
        $admin = User::factory()->create([
            'account_type' => 'admin',
            'name' => 'HR Director',
            'email' => 'hr.director@example.com',
            'password' => bcrypt('password123'),
        ]);

        $candidate = User::factory()->create();
        $profile = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidate->id, 'created_at' => now(), 'updated_at' => now()]);
        $app = DB::table('applications')->insertGetId(['candidate_id' => $profile, 'created_at' => now(), 'updated_at' => now()]);

        $interview = Interview::create([
            'application_id' => $app,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $interviewer = User::factory()->create();
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHour(),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $slot->start_at,
            'end_at' => $slot->end_at,
            'booking_status' => 'confirmed',
        ]);

        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 95.0,
            'internal_notes' => 'Ready for offer.',
            'candidate_feedback' => 'Outstanding interview performance.',
            'feedback_shared_at' => null,
        ]);

        $adminToken = $admin->createToken('admin-api')->plainTextToken;

        // Admin authenticating via Sanctum Bearer token and publishing evaluation -> 200 OK
        $responsePublish = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/interviews/{$interview->id}/evaluations/{$evaluation->id}/publish");

        $responsePublish->assertStatus(200);
        $responsePublish->assertJsonPath('data.is_published', true);
        $this->assertNotNull($evaluation->fresh()->feedback_shared_at);

        // Reset auth guards to ensure fresh token authentication
        $this->app['auth']->forgetGuards();

        // Admin attempting to access candidate-specific view -> 403 Forbidden
        $responseCandidatePortal = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $responseCandidatePortal->assertStatus(403);
    }

    public function test_admin_bearer_token_cannot_schedule_reschedule_cancel_or_evaluate_interview(): void
    {
        $admin = User::factory()->create([
            'account_type' => 'admin',
            'name' => 'System Admin',
            'email' => 'admin.sys@example.com',
            'password' => bcrypt('password123'),
        ]);

        $candidate = User::factory()->create();
        $profile = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidate->id, 'created_at' => now(), 'updated_at' => now()]);
        $app = DB::table('applications')->insertGetId(['candidate_id' => $profile, 'created_at' => now(), 'updated_at' => now()]);

        $interviewer = User::factory()->create();
        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHour(),
            'availability_status' => 'open',
        ]);
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(3),
            'end_at' => Carbon::now()->addDays(3)->addHour(),
            'availability_status' => 'open',
        ]);

        $interview = Interview::create([
            'application_id' => $app,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => $slot1->start_at,
            'end_at' => $slot1->end_at,
            'booking_status' => 'confirmed',
        ]);

        $adminToken = $admin->createToken('admin-api')->plainTextToken;

        // 1. Admin cannot schedule -> 403 Forbidden
        $respSchedule = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/interviews', [
                'application_id' => $app,
                'round_no' => 2,
                'interview_type' => 'online',
                'bookings' => [
                    ['interviewer_id' => $interviewer->id, 'availability_id' => $slot2->id],
                ],
            ]);
        $respSchedule->assertStatus(403);
        $this->app['auth']->forgetGuards();

        // 2. Admin cannot reschedule -> 403 Forbidden
        $respReschedule = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/interviews/{$interview->id}/reschedule", [
                'booking_id' => $booking->id,
                'new_availability_id' => $slot2->id,
            ]);
        $respReschedule->assertStatus(403);
        $this->app['auth']->forgetGuards();

        // 3. Admin cannot cancel -> 403 Forbidden
        $respCancel = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/interviews/{$interview->id}/cancel", [
                'reason' => 'Admin cancellation attempt',
            ]);
        $respCancel->assertStatus(403);
        $this->app['auth']->forgetGuards();

        // 4. Admin cannot submit evaluation -> 403 Forbidden
        $respEval = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/interviews/{$interview->id}/evaluations", [
                'numeric_score' => 88.0,
                'internal_notes' => 'Admin evaluation attempt',
            ]);
        $respEval->assertStatus(403);
        $this->app['auth']->forgetGuards();

        // 5. Admin CAN view interviewer interview details for administrative oversight -> 200 OK
        $respOversight = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson("/api/interviewer/interviews/{$interview->id}");
        $respOversight->assertStatus(200);
    }

    public function test_authorized_company_recruiter_user_can_reschedule_and_cancel_interview(): void
    {
        $recruiterUser = User::factory()->create(['role' => 'recruiter']);
        $candidate = User::factory()->create();
        $profile = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidate->id, 'created_at' => now(), 'updated_at' => now()]);
        $app = DB::table('applications')->insertGetId(['candidate_id' => $profile, 'created_at' => now(), 'updated_at' => now()]);

        $interviewer = User::factory()->create();
        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHour(),
            'availability_status' => 'booked',
        ]);
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(3),
            'end_at' => Carbon::now()->addDays(3)->addHour(),
            'availability_status' => 'open',
        ]);

        $interview = Interview::create([
            'application_id' => $app,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => $slot1->start_at,
            'end_at' => $slot1->end_at,
            'booking_status' => 'confirmed',
        ]);

        $recruiterToken = $recruiterUser->createToken('recruiter-token')->plainTextToken;

        // Recruiter reschedules booking -> 200 OK
        $respReschedule = $this->withHeader('Authorization', 'Bearer ' . $recruiterToken)
            ->postJson("/api/interviews/{$interview->id}/reschedule", [
                'booking_id' => $booking->id,
                'new_availability_id' => $slot2->id,
            ]);
        $respReschedule->assertStatus(200);
        $this->assertEquals('rescheduled', $interview->fresh()->status);
        $this->app['auth']->forgetGuards();

        // Recruiter cancels interview -> 200 OK
        $respCancel = $this->withHeader('Authorization', 'Bearer ' . $recruiterToken)
            ->postJson("/api/interviews/{$interview->id}/cancel", [
                'reason' => 'Candidate requested cancellation via recruiter',
            ]);
        $respCancel->assertStatus(200);
        $this->assertEquals('cancelled', $interview->fresh()->status);
        $this->assertEquals($recruiterUser->id, $booking->fresh()->cancelled_by);
    }
}
