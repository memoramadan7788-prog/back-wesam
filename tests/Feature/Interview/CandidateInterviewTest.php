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

class CandidateInterviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function setUpDatabase(): void
    {
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

        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('jobs') && !Schema::hasColumn('jobs', 'title')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->string('title')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
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

    public function test_unauthenticated_user_cannot_access_interviews(): void
    {
        $response = $this->getJson('/api/candidate/interviews');

        $response->assertStatus(401);
    }

    public function test_candidate_can_list_their_own_interviews_via_ownership_chain(): void
    {
        $candidateUser = User::factory()->create();
        $candidateProfileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $applicationId = DB::table('applications')->insertGetId([
            'candidate_id' => $candidateProfileId,
            'job_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherCandidateUser = User::factory()->create();
        $otherProfileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $otherCandidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherApplicationId = DB::table('applications')->insertGetId([
            'candidate_id' => $otherProfileId,
            'job_id' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create interview for authenticated candidate
        $interview = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'meeting_link' => 'https://meet.google.com/abc-xyz',
        ]);

        // Create interview for other candidate
        Interview::create([
            'application_id' => $otherApplicationId,
            'round_no' => 1,
            'interview_type' => 'onsite',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($candidateUser)
            ->getJson('/api/candidate/interviews');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $interview->id);
        $response->assertJsonPath('data.0.application_id', $applicationId);
    }

    public function test_candidate_can_view_their_own_interview_details(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $applicationId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interview = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 2,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'meeting_link' => 'https://zoom.us/j/123456789',
        ]);

        $response = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $interview->id)
            ->assertJsonPath('data.round_no', 2)
            ->assertJsonPath('data.meeting_link', 'https://zoom.us/j/123456789');
    }

    public function test_idor_prevention_candidate_cannot_view_another_candidates_interview(): void
    {
        $candidateA = User::factory()->create();
        $profileAId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appAId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileAId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $candidateB = User::factory()->create();
        $profileBId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appBId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileBId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interviewA = Interview::create([
            'application_id' => $appAId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        // Candidate B attempts to view Candidate A's interview ID
        $response = $this->actingAs($candidateB)
            ->getJson("/api/candidate/interviews/{$interviewA->id}");

        $response->assertStatus(403);
    }

    public function test_candidate_cannot_see_internal_notes_or_numeric_scores(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $applicationId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $evaluator = User::factory()->create();

        $interview = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);

        // Create evaluation with private internal notes and numeric score, and shared candidate feedback
        InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $evaluator->id,
            'numeric_score' => 92.5,
            'internal_notes' => 'Internal note: candidate has strong problem solving but weak on SQL.',
            'candidate_feedback' => 'Great technical depth shown in live coding.',
            'feedback_shared_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $response->assertStatus(200);
        // internal_notes MUST NEVER be exposed to candidate even when published
        $response->assertJsonMissing(['internal_notes']);
        // numeric_score and candidate_feedback are exposed because feedback_shared_at is set
        $response->assertJsonPath('data.shared_feedback.0.numeric_score', 92.5);
        $response->assertJsonPath('data.shared_feedback.0.feedback', 'Great technical depth shown in live coding.');
    }

    public function test_candidate_cannot_see_unshared_candidate_feedback_when_feedback_shared_at_is_null(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $applicationId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $evaluator = User::factory()->create();

        $interview = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);

        // Create evaluation with feedback_shared_at = null
        InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $evaluator->id,
            'numeric_score' => 85.0,
            'internal_notes' => 'Evaluation in review',
            'candidate_feedback' => 'Draft feedback not yet approved for release',
            'feedback_shared_at' => null,
        ]);

        $response = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $response->assertStatus(200);
        $response->assertJsonMissing(['shared_feedback']);
        $response->assertJsonMissing(['Draft feedback not yet approved for release']);
    }

    public function test_candidate_interview_page_provides_schedule_bookings_and_application_job_context(): void
    {
        $candidateUser = User::factory()->create();
        $interviewer = User::factory()->create();

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Acme Corporation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::table('jobs')->insertGetId([
            'title' => 'Senior Backend Engineer',
            'company_id' => $companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $applicationId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'job_id' => $jobId,
            'status' => 'interviewing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $startAt = Carbon::now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0);
        $endAt = $startAt->copy()->addMinutes(60);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'location' => null,
            'meeting_link' => 'https://meet.google.com/xyz-uvw-rst',
        ]);

        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'booking_status' => 'confirmed',
        ]);

        InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $interviewer->id,
            'numeric_score' => 90.0,
            'internal_notes' => 'Confidential feedback',
            'candidate_feedback' => 'Candidate demonstrated great proficiency.',
            'feedback_shared_at' => Carbon::now(),
        ]);

        // Test Candidate Interview List (Page index)
        $listResponse = $this->actingAs($candidateUser)
            ->getJson('/api/candidate/interviews');

        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('data.0.id', $interview->id);
        $listResponse->assertJsonPath('data.0.interview_type', 'online');
        $listResponse->assertJsonPath('data.0.status', 'scheduled');
        $listResponse->assertJsonPath('data.0.meeting_link', 'https://meet.google.com/xyz-uvw-rst');
        $listResponse->assertJsonPath('data.0.start_at', $startAt->toIso8601String());
        $listResponse->assertJsonPath('data.0.end_at', $endAt->toIso8601String());
        $listResponse->assertJsonPath('data.0.booking_status', 'confirmed');
        $listResponse->assertJsonPath('data.0.application.job_title', 'Senior Backend Engineer');
        $listResponse->assertJsonPath('data.0.application.company_name', 'Acme Corporation');
        $listResponse->assertJsonPath('data.0.application.status', 'interviewing');
        $listResponse->assertJsonPath('data.0.bookings.0.id', $booking->id);
        $listResponse->assertJsonPath('data.0.bookings.0.booking_status', 'confirmed');
        $listResponse->assertJsonPath('data.0.shared_feedback.0.feedback', 'Candidate demonstrated great proficiency.');

        // Test Candidate Interview Details (Page detail modal/view)
        $detailResponse = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonPath('data.id', $interview->id);
        $detailResponse->assertJsonPath('data.start_at', $startAt->toIso8601String());
        $detailResponse->assertJsonPath('data.end_at', $endAt->toIso8601String());
        $detailResponse->assertJsonPath('data.booking_status', 'confirmed');
        $detailResponse->assertJsonPath('data.application.job_title', 'Senior Backend Engineer');
        $detailResponse->assertJsonPath('data.application.company_name', 'Acme Corporation');
        $detailResponse->assertJsonMissing(['numeric_score', 'internal_notes']);
    }

    public function test_candidate_can_filter_interviews_by_date_range_for_interview_page(): void
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

        // Interview 1: tomorrow
        $int1 = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDay(),
            'end_at' => Carbon::now()->addDay()->addHour(),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $int1->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => Carbon::now()->addDay(),
            'end_at' => Carbon::now()->addDay()->addHour(),
            'booking_status' => 'confirmed',
        ]);

        // Interview 2: in 2 weeks
        $int2 = Interview::create([
            'application_id' => $appId,
            'round_no' => 2,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addWeeks(2),
            'end_at' => Carbon::now()->addWeeks(2)->addHour(),
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $int2->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot2->id,
            'start_at' => Carbon::now()->addWeeks(2),
            'end_at' => Carbon::now()->addWeeks(2)->addHour(),
            'booking_status' => 'confirmed',
        ]);

        // Filter range for next 3 days only
        $response = $this->actingAs($candidateUser)->getJson('/api/candidate/interviews?' . http_build_query([
            'from' => Carbon::now()->toDateString(),
            'to' => Carbon::now()->addDays(3)->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $int1->id);
    }

    public function test_authenticated_candidate_with_no_interviews_receives_empty_collection_with_pagination_metadata(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Another candidate has an interview in the system
        $otherUser = User::factory()->create();
        $otherProfileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $otherUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherAppId = DB::table('applications')->insertGetId([
            'candidate_id' => $otherProfileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Interview::create([
            'application_id' => $otherAppId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($candidateUser)
            ->getJson('/api/candidate/interviews');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
        $response->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            'applied_filters',
        ]);
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonPath('meta.current_page', 1);
    }

    public function test_authenticated_candidate_requesting_nonexistent_interview_id_receives_404(): void
    {
        $candidateUser = User::factory()->create();

        $response = $this->actingAs($candidateUser)
            ->getJson('/api/candidate/interviews/99999');

        $response->assertStatus(404);
    }

    public function test_scheduling_conflict_is_detected_across_different_applications_for_same_candidate(): void
    {
        $service = app(\App\Services\InterviewSchedulingService::class);

        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Candidate has two different applications (e.g. applied to two jobs)
        $app1Id = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $app2Id = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interviewer1 = User::factory()->create();

        $startAt = Carbon::now()->addDays(3)->setHour(10)->setMinute(0)->setSecond(0);
        $endAt = $startAt->copy()->addMinutes(45);

        // Schedule interview on Application 1
        $int1 = Interview::create([
            'application_id' => $app1Id,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer1->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $int1->id,
            'interviewer_id' => $interviewer1->id,
            'availability_id' => $slot1->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'booking_status' => 'confirmed',
        ]);

        // Conflict check on Application 2 during overlapping time must return true
        $hasConflict = $service->hasCandidateBookingConflict(
            $app2Id,
            $startAt->copy()->addMinutes(15),
            $endAt->copy()->addMinutes(15)
        );

        $this->assertTrue($hasConflict, 'Candidate overlap across different applications must be detected');

        // Conflict check outside window must return false
        $noConflict = $service->hasCandidateBookingConflict(
            $app2Id,
            $endAt->copy()->addHour(),
            $endAt->copy()->addHours(2)
        );

        $this->assertFalse($noConflict, 'Candidate should not conflict outside scheduled time window');
    }

    public function test_candidate_interview_details_exposes_rounds_timeline_in_chronological_order(): void
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

        // Round 1: Past completed interview
        $pastStart = Carbon::now()->subDays(5)->setHour(10)->setMinute(0);
        $pastEnd = $pastStart->copy()->addHour();
        $int1 = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
            'meeting_link' => 'https://meet.google.com/round-1',
        ]);
        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $pastStart,
            'end_at' => $pastEnd,
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $int1->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => $pastStart,
            'end_at' => $pastEnd,
            'booking_status' => 'confirmed',
        ]);

        // Round 2: Current scheduled interview (viewed)
        $currentStart = Carbon::now()->addDays(2)->setHour(14)->setMinute(0);
        $currentEnd = $currentStart->copy()->addHour();
        $int2 = Interview::create([
            'application_id' => $appId,
            'round_no' => 2,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'meeting_link' => 'https://meet.google.com/round-2',
        ]);
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $currentStart,
            'end_at' => $currentEnd,
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $int2->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot2->id,
            'start_at' => $currentStart,
            'end_at' => $currentEnd,
            'booking_status' => 'confirmed',
        ]);

        // Round 3: Upcoming round without scheduled booking yet
        $int3 = Interview::create([
            'application_id' => $appId,
            'round_no' => 3,
            'interview_type' => 'onsite',
            'status' => 'scheduled',
            'location' => 'HQ Building, Room 402',
        ]);

        $response = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$int2->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $int2->id);
        $response->assertJsonPath('data.round_no', 2);
        $response->assertJsonPath('data.interview_type', 'online');
        $response->assertJsonPath('data.is_online', true);

        // Verify rounds timeline
        $rounds = $response->json('data.rounds');
        $this->assertIsArray($rounds);
        $this->assertCount(3, $rounds);

        // Round 1 assertions
        $this->assertEquals(1, $rounds[0]['round_no']);
        $this->assertEquals('completed', $rounds[0]['status']);
        $this->assertEquals('past', $rounds[0]['round_state']);
        $this->assertFalse($rounds[0]['is_current']);
        $this->assertFalse($rounds[0]['is_current_stage']);
        $this->assertEquals($pastStart->toIso8601String(), $rounds[0]['start_at']);

        // Round 2 assertions (future scheduled round and current active stage)
        $this->assertEquals(2, $rounds[1]['round_no']);
        $this->assertEquals('scheduled', $rounds[1]['status']);
        $this->assertEquals('upcoming', $rounds[1]['round_state']); // Fixed: future scheduled round is upcoming, never incorrectly marked current
        $this->assertTrue($rounds[1]['is_current']);
        $this->assertTrue($rounds[1]['is_current_stage']);
        $this->assertEquals($currentStart->toIso8601String(), $rounds[1]['start_at']);

        // Round 3 assertions (unscheduled upcoming round)
        $this->assertEquals(3, $rounds[2]['round_no']);
        $this->assertEquals('scheduled', $rounds[2]['status']);
        $this->assertEquals('upcoming', $rounds[2]['round_state']);
        $this->assertFalse($rounds[2]['is_current']);
        $this->assertFalse($rounds[2]['is_current_stage']);
        $this->assertNull($rounds[2]['start_at']);
        $this->assertNull($rounds[2]['end_at']);
        $this->assertNull($rounds[2]['booking_status']);
        $this->assertEquals('onsite', $rounds[2]['interview_type']);
        $this->assertFalse($rounds[2]['is_online']);
        $this->assertEquals('HQ Building, Room 402', $rounds[2]['location']);
    }

    public function test_candidate_interview_distinguishes_online_and_onsite_types(): void
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

        // Online interview
        $onlineInt = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'meeting_link' => 'https://zoom.us/j/123456789',
            'location' => null,
        ]);

        // On-site interview
        $onsiteInt = Interview::create([
            'application_id' => $appId,
            'round_no' => 2,
            'interview_type' => 'onsite',
            'status' => 'scheduled',
            'meeting_link' => null,
            'location' => 'Main Campus - Floor 3',
        ]);

        $onlineResponse = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$onlineInt->id}");
        $onlineResponse->assertStatus(200);
        $onlineResponse->assertJsonPath('data.interview_type', 'online');
        $onlineResponse->assertJsonPath('data.is_online', true);
        $onlineResponse->assertJsonPath('data.meeting_link', 'https://zoom.us/j/123456789');
        $onlineResponse->assertJsonPath('data.location', null);

        $onsiteResponse = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$onsiteInt->id}");
        $onsiteResponse->assertStatus(200);
        $onsiteResponse->assertJsonPath('data.interview_type', 'onsite');
        $onsiteResponse->assertJsonPath('data.is_online', false);
        $onsiteResponse->assertJsonPath('data.location', 'Main Campus - Floor 3');
        $onsiteResponse->assertJsonPath('data.meeting_link', null);
    }

    public function test_candidate_interview_handles_unscheduled_interview_and_null_bookings_safely(): void
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

        // Interview with zero bookings created
        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'meeting_link' => null,
            'location' => null,
        ]);

        $response = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $interview->id);
        $response->assertJsonPath('data.start_at', null);
        $response->assertJsonPath('data.end_at', null);
        $response->assertJsonPath('data.booking_status', null);
        $response->assertJsonPath('data.bookings', []);
        $response->assertJsonPath('data.instructions', null);
        $response->assertJsonPath('data.evaluation_status', 'pending');
    }

    public function test_candidate_evaluation_status_transitions_from_pending_to_submitted_to_published(): void
    {
        $candidateUser = User::factory()->create();
        $evaluator = User::factory()->create();

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

        // State 1: No evaluations submitted yet -> 'pending'
        $response1 = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");
        $response1->assertStatus(200);
        $response1->assertJsonPath('data.evaluation_status', 'pending');
        $response1->assertJsonMissing(['numeric_score', 'candidate_feedback', 'internal_notes']);

        // State 2: Evaluator submits evaluation, but not published -> 'submitted'
        $eval = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $evaluator->id,
            'numeric_score' => 96.5,
            'internal_notes' => 'Secret deliberation between interviewers',
            'candidate_feedback' => 'Candidate demonstrated stellar architecture knowledge',
            'feedback_shared_at' => null,
        ]);

        $response2 = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");
        $response2->assertStatus(200);
        $response2->assertJsonPath('data.evaluation_status', 'submitted');
        $response2->assertJsonMissing(['numeric_score', 'candidate_feedback', 'internal_notes']);

        // State 3: HR/Interviewer publishes evaluation -> 'published'
        $eval->update(['feedback_shared_at' => Carbon::now()]);

        $response3 = $this->actingAs($candidateUser)
            ->getJson("/api/candidate/interviews/{$interview->id}");
        $response3->assertStatus(200);
        $response3->assertJsonPath('data.evaluation_status', 'published');
        $response3->assertJsonPath('data.numeric_score', 96.5);
        $response3->assertJsonPath('data.candidate_feedback', 'Candidate demonstrated stellar architecture knowledge');
        $this->assertNotNull($response3->json('data.feedback_shared_at'));
        // Internal notes must NEVER be exposed
        $response3->assertJsonMissing(['internal_notes', 'Secret deliberation between interviewers']);
    }

    public function test_candidate_can_sort_interviews_by_round_number(): void
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

        $intRound1 = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        $intRound2 = Interview::create([
            'application_id' => $appId,
            'round_no' => 2,
            'interview_type' => 'onsite',
            'status' => 'scheduled',
        ]);

        // Ascending sort by round
        $ascResponse = $this->actingAs($candidateUser)
            ->getJson('/api/candidate/interviews?sort=round_asc');
        $ascResponse->assertStatus(200);
        $ascResponse->assertJsonPath('data.0.round_no', 1);
        $ascResponse->assertJsonPath('data.1.round_no', 2);

        // Descending sort by round
        $descResponse = $this->actingAs($candidateUser)
            ->getJson('/api/candidate/interviews?sort=round_desc');
        $descResponse->assertStatus(200);
        $descResponse->assertJsonPath('data.0.round_no', 2);
        $descResponse->assertJsonPath('data.1.round_no', 1);
    }

    public function test_candidate_rounds_timeline_prevents_cross_candidate_data_leakage(): void
    {
        $candidateA = User::factory()->create();
        $candidateB = User::factory()->create();

        $profileA = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidateA->id, 'created_at' => now(), 'updated_at' => now()]);
        $profileB = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidateB->id, 'created_at' => now(), 'updated_at' => now()]);

        $appA = DB::table('applications')->insertGetId(['candidate_id' => $profileA, 'created_at' => now(), 'updated_at' => now()]);
        $appB = DB::table('applications')->insertGetId(['candidate_id' => $profileB, 'created_at' => now(), 'updated_at' => now()]);

        $interviewA1 = Interview::create(['application_id' => $appA, 'round_no' => 1, 'interview_type' => 'online', 'status' => 'completed']);
        $interviewA2 = Interview::create(['application_id' => $appA, 'round_no' => 2, 'interview_type' => 'online', 'status' => 'scheduled']);
        $interviewB1 = Interview::create(['application_id' => $appB, 'round_no' => 1, 'interview_type' => 'online', 'status' => 'scheduled']);

        $response = $this->actingAs($candidateA)->getJson("/api/candidate/interviews/{$interviewA2->id}");
        $response->assertStatus(200);

        $roundIds = collect($response->json('data.rounds'))->pluck('id')->all();
        $this->assertContains($interviewA1->id, $roundIds);
        $this->assertContains($interviewA2->id, $roundIds);
        $this->assertNotContains($interviewB1->id, $roundIds, 'Candidate A must never see rounds from Candidate B');
    }

    public function test_cancelled_interview_round_is_marked_as_cancelled_in_timeline(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidateUser->id, 'created_at' => now(), 'updated_at' => now()]);
        $appId = DB::table('applications')->insertGetId(['candidate_id' => $profileId, 'created_at' => now(), 'updated_at' => now()]);

        $cancelledRound = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'cancelled',
        ]);
        $activeRound = Interview::create([
            'application_id' => $appId,
            'round_no' => 2,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($candidateUser)->getJson("/api/candidate/interviews/{$activeRound->id}");
        $response->assertStatus(200);

        $rounds = $response->json('data.rounds');
        $this->assertEquals(1, $rounds[0]['round_no']);
        $this->assertEquals('cancelled', $rounds[0]['round_state']);
        $this->assertEquals('cancelled', $rounds[0]['status']);
    }

    public function test_round_state_distinguishes_in_progress_interview_happening_right_now(): void
    {
        $candidateUser = User::factory()->create();
        $interviewer = User::factory()->create();

        $profileId = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidateUser->id, 'created_at' => now(), 'updated_at' => now()]);
        $appId = DB::table('applications')->insertGetId(['candidate_id' => $profileId, 'created_at' => now(), 'updated_at' => now()]);

        // Interview currently happening right now (start 15 min ago, end in 45 min)
        $startAt = Carbon::now()->subMinutes(15);
        $endAt = Carbon::now()->addMinutes(45);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'meeting_link' => 'https://meet.google.com/live-now',
        ]);
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'availability_status' => 'booked',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($candidateUser)->getJson("/api/candidate/interviews/{$interview->id}");
        $response->assertStatus(200);

        $rounds = $response->json('data.rounds');
        $this->assertCount(1, $rounds);
        $this->assertEquals(1, $rounds[0]['round_no']);
        // Round state MUST be 'in_progress' when happening right now, distinguishing it from future upcoming and pending current stages
        $this->assertEquals('in_progress', $rounds[0]['round_state']);
        $this->assertTrue($rounds[0]['is_current_stage']);
        $this->assertTrue($rounds[0]['is_current']);
    }

    public function test_mixed_evaluation_publication_states_expose_only_published_evaluation_data_and_protect_unpublished_drafts(): void
    {
        $candidateUser = User::factory()->create();
        $evaluatorA = User::factory()->create();
        $evaluatorB = User::factory()->create();

        $profileId = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidateUser->id, 'created_at' => now(), 'updated_at' => now()]);
        $appId = DB::table('applications')->insertGetId(['candidate_id' => $profileId, 'created_at' => now(), 'updated_at' => now()]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);

        // Evaluation A: Published
        $evalA = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $evaluatorA->id,
            'numeric_score' => 93.5,
            'internal_notes' => 'Evaluation A internal deliberations - strictly private',
            'candidate_feedback' => 'Evaluation A official published feedback',
            'feedback_shared_at' => Carbon::now(),
        ]);

        // Evaluation B: Unpublished draft
        $evalB = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $evaluatorB->id,
            'numeric_score' => 55.0,
            'internal_notes' => 'Evaluation B secret notes - candidate should be rejected',
            'candidate_feedback' => 'Evaluation B unpublished draft notes',
            'feedback_shared_at' => null,
        ]);

        $response = $this->actingAs($candidateUser)->getJson("/api/candidate/interviews/{$interview->id}");
        $response->assertStatus(200);

        // Evaluation status must reflect that official published evaluation is available
        $response->assertJsonPath('data.evaluation_status', 'published');

        // Only Evaluation A must be exposed in shared_feedback
        $response->assertJsonCount(1, 'data.shared_feedback');
        $response->assertJsonPath('data.shared_feedback.0.id', $evalA->id);
        $response->assertJsonPath('data.shared_feedback.0.numeric_score', 93.5);
        $response->assertJsonPath('data.shared_feedback.0.candidate_feedback', 'Evaluation A official published feedback');

        // Top-level fields expose the published evaluation
        $response->assertJsonPath('data.numeric_score', 93.5);
        $response->assertJsonPath('data.candidate_feedback', 'Evaluation A official published feedback');
        $this->assertNotNull($response->json('data.feedback_shared_at'));

        // Unpublished evaluation B data must NEVER be exposed
        $response->assertJsonMissing([
            'Evaluation A internal deliberations - strictly private',
            'Evaluation B secret notes - candidate should be rejected',
            'Evaluation B unpublished draft notes',
            55.0,
        ]);
    }

    public function test_candidate_can_view_interview_instructions_in_list_and_details(): void
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

        $instructionsText = 'Please test your audio and webcam 15 minutes before the interview.';

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
            'meeting_link' => 'https://meet.google.com/test-room',
            'instructions' => $instructionsText,
        ]);

        // 1. Details endpoint (GET /api/candidate/interviews/{id})
        $detailResponse = $this->actingAs($candidateUser)->getJson("/api/candidate/interviews/{$interview->id}");
        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonPath('data.instructions', $instructionsText);

        // 2. List endpoint (GET /api/candidate/interviews)
        $listResponse = $this->actingAs($candidateUser)->getJson('/api/candidate/interviews');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('data.0.instructions', $instructionsText);
    }
}

