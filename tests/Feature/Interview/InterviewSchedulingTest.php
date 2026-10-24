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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InterviewSchedulingTest extends TestCase
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

    public function test_scheduling_creates_interview_and_interview_bookings(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $applicationId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'job_id' => 101,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interviewer1 = User::factory()->create();
        $interviewer2 = User::factory()->create();

        $start = Carbon::now()->addDays(2)->setTime(10, 0, 0);
        $end = $start->copy()->addMinutes(60);

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer1->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'open',
        ]);

        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer2->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'open',
        ]);

        $payload = [
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'meeting_link' => 'https://meet.google.com/xyz',
            'bookings' => [
                ['interviewer_id' => $interviewer1->id, 'availability_id' => $slot1->id],
                ['interviewer_id' => $interviewer2->id, 'availability_id' => $slot2->id],
            ],
        ];

        $response = $this->actingAs($scheduler)
            ->postJson('/api/interviews', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('interviews', [
            'application_id' => $applicationId,
            'round_no' => 1,
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('interview_bookings', [
            'interviewer_id' => $interviewer1->id,
            'availability_id' => $slot1->id,
            'booking_status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('interview_bookings', [
            'interviewer_id' => $interviewer2->id,
            'availability_id' => $slot2->id,
            'booking_status' => 'confirmed',
        ]);

        // Assert slots were marked booked
        $this->assertEquals('booked', $slot1->fresh()->availability_status);
        $this->assertEquals('booked', $slot2->fresh()->availability_status);
    }

    public function test_scheduling_fails_when_availability_slot_is_not_open(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
        $interviewer = User::factory()->create();

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addMinutes(60),
            'availability_status' => 'booked', // Already booked!
        ]);

        $payload = [
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
            ],
        ];

        $response = $this->actingAs($scheduler)
            ->postJson('/api/interviews', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['bookings.0.availability_id']);
    }

    public function test_scheduling_prevents_candidate_overlap(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
        $interviewer1 = User::factory()->create();
        $interviewer2 = User::factory()->create();

        $start = Carbon::now()->addDays(2)->setTime(10, 0, 0);
        $end = $start->copy()->addMinutes(60);

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer1->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        // First interview
        $interview1 = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview1->id,
            'interviewer_id' => $interviewer1->id,
            'availability_id' => $slot1->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        // Attempt second interview for same application during overlapping time: 10:30 - 11:30
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer2->id,
            'start_at' => $start->copy()->addMinutes(30),
            'end_at' => $end->copy()->addMinutes(30),
            'availability_status' => 'open',
        ]);

        $overlapPayload = [
            'application_id' => $applicationId,
            'round_no' => 2,
            'interview_type' => 'online',
            'bookings' => [
                ['interviewer_id' => $interviewer2->id, 'availability_id' => $slot2->id],
            ],
        ];

        $response = $this->actingAs($scheduler)
            ->postJson('/api/interviews', $overlapPayload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['application_id']);
    }

    public function test_interview_booking_can_be_rescheduled(): void
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
        $interviewer = User::factory()->create();

        $start1 = Carbon::now()->addDays(2)->setTime(10, 0, 0);
        $end1 = $start1->copy()->addMinutes(60);

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start1,
            'end_at' => $end1,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => $start1,
            'end_at' => $end1,
            'booking_status' => 'confirmed',
        ]);

        // New available slot for interviewer
        $start2 = Carbon::now()->addDays(3)->setTime(14, 0, 0);
        $end2 = $start2->copy()->addMinutes(60);

        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start2,
            'end_at' => $end2,
            'availability_status' => 'open',
        ]);

        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/reschedule", [
                'booking_id' => $booking->id,
                'new_availability_id' => $slot2->id,
                'reason' => 'Candidate requested afternoon slot',
            ]);

        $response->assertStatus(200);

        // Assert booking updated
        $booking->refresh();
        $this->assertEquals($slot2->id, $booking->availability_id);
        $this->assertEquals($start2->toDateTimeString(), $booking->start_at->toDateTimeString());

        // Assert new slot is booked and old slot is released to open
        $this->assertEquals('booked', $slot2->fresh()->availability_status);
        $this->assertEquals('open', $slot1->fresh()->availability_status);
    }

    public function test_interview_can_be_cancelled_and_releases_availability(): void
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
        $interviewer = User::factory()->create();

        $start = Carbon::now()->addDays(2)->setTime(10, 0, 0);
        $end = $start->copy()->addMinutes(60);

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($interviewer)
            ->postJson("/api/interviews/{$interview->id}/cancel", [
                'reason' => 'Hiring position closed',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $interview->fresh()->status);
        $this->assertEquals('cancelled', $booking->fresh()->booking_status);
        $this->assertEquals($interviewer->id, $booking->fresh()->cancelled_by);

        // Assert availability slot was released back to open
        $this->assertEquals('open', $slot->fresh()->availability_status);
    }

    public function test_candidate_cannot_schedule_interview_via_api(): void
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

        $interviewer = User::factory()->create();
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHour(),
            'availability_status' => 'open',
        ]);

        $response = $this->actingAs($candidateUser)
            ->postJson('/api/interviews', [
                'application_id' => $applicationId,
                'round_no' => 1,
                'interview_type' => 'online',
                'bookings' => [
                    ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_candidate_cannot_reschedule_interview_via_api(): void
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

        $interviewer = User::factory()->create();
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHour(),
            'availability_status' => 'booked',
        ]);
        $newSlot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(3),
            'end_at' => Carbon::now()->addDays(3)->addHour(),
            'availability_status' => 'open',
        ]);

        $interview = Interview::create([
            'application_id' => $applicationId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot->id,
            'start_at' => $slot->start_at,
            'end_at' => $slot->end_at,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($candidateUser)
            ->postJson("/api/interviews/{$interview->id}/reschedule", [
                'booking_id' => $booking->id,
                'new_availability_id' => $newSlot->id,
                'reason' => 'Candidate trying to reschedule',
            ]);

        $response->assertStatus(403);
    }

    public function test_candidate_cannot_cancel_interview_via_api(): void
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
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($candidateUser)
            ->postJson("/api/interviews/{$interview->id}/cancel", [
                'reason' => 'Candidate cancelling',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_model_is_handled_safely_and_authorized_via_interview_policy(): void
    {
        $admin = User::factory()->create([
            'account_type' => 'admin',
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidateUser->id, 'created_at' => now(), 'updated_at' => now()]);
        $appId = DB::table('applications')->insertGetId(['candidate_id' => $profileId, 'created_at' => now(), 'updated_at' => now()]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $evaluator = User::factory()->create();
        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $evaluator->id,
            'numeric_score' => 90.0,
            'internal_notes' => 'Evaluation note',
            'candidate_feedback' => 'Good job',
        ]);

        // Verifies no TypeError is thrown and Admin is denied operational actions while retaining oversight and evaluation publishing
        $this->assertFalse(Gate::forUser($admin)->allows('schedule', Interview::class));
        $this->assertFalse(Gate::forUser($admin)->allows('reschedule', $interview));
        $this->assertFalse(Gate::forUser($admin)->allows('cancel', $interview));
        $this->assertFalse(Gate::forUser($admin)->allows('evaluate', $interview));
        $this->assertTrue(Gate::forUser($admin)->allows('viewInterviewer', $interview));
        $this->assertTrue(Gate::forUser($admin)->allows('publishEvaluation', [$interview, $evaluation]));

        // Admin must not be authorized to view interview via candidate-specific ownership portal
        $this->assertFalse(Gate::forUser($admin)->allows('viewCandidate', $interview));
    }

    public function test_candidate_is_denied_management_and_evaluations_via_interview_policy(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidateUser->id, 'created_at' => now(), 'updated_at' => now()]);
        $appId = DB::table('applications')->insertGetId(['candidate_id' => $profileId, 'created_at' => now(), 'updated_at' => now()]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        $evaluator = User::factory()->create();
        $evaluation = InterviewEvaluation::create([
            'interview_id' => $interview->id,
            'evaluator_id' => $evaluator->id,
            'numeric_score' => 88.0,
            'internal_notes' => 'Candidate internal note',
        ]);

        $this->assertFalse(Gate::forUser($candidateUser)->allows('schedule', Interview::class));
        $this->assertFalse(Gate::forUser($candidateUser)->allows('reschedule', $interview));
        $this->assertFalse(Gate::forUser($candidateUser)->allows('cancel', $interview));
        $this->assertFalse(Gate::forUser($candidateUser)->allows('evaluate', $interview));
        $this->assertFalse(Gate::forUser($candidateUser)->allows('publishEvaluation', [$interview, $evaluation]));
    }

    public function test_unauthorized_user_without_recruitment_permissions_cannot_schedule_or_cancel(): void
    {
        $unauthorizedUser = User::factory()->create();
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId(['user_id' => $candidateUser->id, 'created_at' => now(), 'updated_at' => now()]);
        $appId = DB::table('applications')->insertGetId(['candidate_id' => $profileId, 'created_at' => now(), 'updated_at' => now()]);

        $interviewer = User::factory()->create();
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHour(),
            'availability_status' => 'open',
        ]);

        $interview = Interview::create([
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);

        // 1. Cannot schedule -> 403 Forbidden
        $respSchedule = $this->actingAs($unauthorizedUser)
            ->postJson('/api/interviews', [
                'application_id' => $appId,
                'round_no' => 1,
                'interview_type' => 'online',
                'bookings' => [
                    ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
                ],
            ]);
        $respSchedule->assertStatus(403);

        // 2. Cannot cancel -> 403 Forbidden
        $respCancel = $this->actingAs($unauthorizedUser)
            ->postJson("/api/interviews/{$interview->id}/cancel", [
                'reason' => 'Unauthorized cancellation attempt',
            ]);
        $respCancel->assertStatus(403);
    }

    public function test_scheduling_with_instructions_persists_and_returns_instructions(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
            'start_at' => Carbon::now()->addDays(3)->setTime(14, 0),
            'end_at' => Carbon::now()->addDays(3)->setTime(15, 0),
            'availability_status' => 'open',
        ]);

        $instructionsText = 'Please prepare a 10-minute presentation on system design and bring your valid ID.';

        $payload = [
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'instructions' => $instructionsText,
            'meeting_link' => 'https://meet.google.com/test-instructions',
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
            ],
        ];

        $response = $this->actingAs($scheduler)->postJson('/api/interviews', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.instructions', $instructionsText);

        $this->assertDatabaseHas('interviews', [
            'application_id' => $appId,
            'instructions' => $instructionsText,
        ]);
    }

    public function test_scheduling_without_instructions_handles_null_safely(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
            'start_at' => Carbon::now()->addDays(3)->setTime(10, 0),
            'end_at' => Carbon::now()->addDays(3)->setTime(11, 0),
            'availability_status' => 'open',
        ]);

        $payload = [
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'instructions' => null,
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
            ],
        ];

        $response = $this->actingAs($scheduler)->postJson('/api/interviews', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.instructions', null);

        $this->assertDatabaseHas('interviews', [
            'application_id' => $appId,
            'instructions' => null,
        ]);
    }

    public function test_scheduling_rejects_invalid_instructions_data(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
            'start_at' => Carbon::now()->addDays(3),
            'end_at' => Carbon::now()->addDays(3)->addHour(),
            'availability_status' => 'open',
        ]);

        // Array passed instead of string
        $response = $this->actingAs($scheduler)->postJson('/api/interviews', [
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'instructions' => ['not', 'a', 'string'],
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['instructions']);
    }

    public function test_rescheduling_preserves_existing_instructions(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(3)->setTime(10, 0),
            'end_at' => Carbon::now()->addDays(3)->setTime(11, 0),
            'availability_status' => 'open',
        ]);
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(4)->setTime(14, 0),
            'end_at' => Carbon::now()->addDays(4)->setTime(15, 0),
            'availability_status' => 'open',
        ]);

        $instructionsText = 'Do not erase these instructions on reschedule.';

        $createResp = $this->actingAs($scheduler)->postJson('/api/interviews', [
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'instructions' => $instructionsText,
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slot1->id],
            ],
        ]);
        $createResp->assertStatus(201);
        $interviewId = $createResp->json('data.id');
        $bookingId = $createResp->json('data.bookings.0.id');

        // Reschedule
        $rescheduleResp = $this->actingAs($scheduler)->postJson("/api/interviews/{$interviewId}/reschedule", [
            'booking_id' => $bookingId,
            'new_availability_id' => $slot2->id,
        ]);

        $rescheduleResp->assertStatus(200);
        $rescheduleResp->assertJsonPath('data.instructions', $instructionsText);

        $this->assertDatabaseHas('interviews', [
            'id' => $interviewId,
            'status' => 'rescheduled',
            'instructions' => $instructionsText,
        ]);
    }

    public function test_interview_type_validation_accepts_online_and_on_site(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
        $slotOnline = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2)->setTime(10, 0),
            'end_at' => Carbon::now()->addDays(2)->setTime(11, 0),
            'availability_status' => 'open',
        ]);
        $slotOnsite = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2)->setTime(12, 0),
            'end_at' => Carbon::now()->addDays(2)->setTime(13, 0),
            'availability_status' => 'open',
        ]);

        // 1. online
        $respOnline = $this->actingAs($scheduler)->postJson('/api/interviews', [
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'online',
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slotOnline->id],
            ],
        ]);
        $respOnline->assertStatus(201);
        $respOnline->assertJsonPath('data.interview_type', 'online');

        // 2. on_site
        $respOnsite = $this->actingAs($scheduler)->postJson('/api/interviews', [
            'application_id' => $appId,
            'round_no' => 2,
            'interview_type' => 'on_site',
            'location' => 'Building B, Floor 2',
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slotOnsite->id],
            ],
        ]);
        $respOnsite->assertStatus(201);
        $respOnsite->assertJsonPath('data.interview_type', 'on_site');
    }

    public function test_interview_type_validation_normalizes_onsite_alias_to_canonical_on_site(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
            'start_at' => Carbon::now()->addDays(3)->setTime(15, 0),
            'end_at' => Carbon::now()->addDays(3)->setTime(16, 0),
            'availability_status' => 'open',
        ]);

        // Sending legacy/compatibility alias 'onsite'
        $response = $this->actingAs($scheduler)->postJson('/api/interviews', [
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'onsite',
            'location' => 'Conference Room 101',
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
            ],
        ]);

        $response->assertStatus(201);
        // Normalized to canonical 'on_site'
        $response->assertJsonPath('data.interview_type', 'on_site');

        $this->assertDatabaseHas('interviews', [
            'application_id' => $appId,
            'interview_type' => 'on_site',
        ]);
    }

    public function test_interview_type_validation_rejects_invalid_values(): void
    {
        $scheduler = User::factory()->create(['role' => 'recruiter']);
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
            'start_at' => Carbon::now()->addDays(3),
            'end_at' => Carbon::now()->addDays(3)->addHour(),
            'availability_status' => 'open',
        ]);

        // Arbitrary invalid string
        $response = $this->actingAs($scheduler)->postJson('/api/interviews', [
            'application_id' => $appId,
            'round_no' => 1,
            'interview_type' => 'phone_call',
            'bookings' => [
                ['interviewer_id' => $interviewer->id, 'availability_id' => $slot->id],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['interview_type']);
    }
}
