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

class CompanyInterviewAndEvaluationTest extends TestCase
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

        if (!Schema::hasTable('company_employees')) {
            Schema::create('company_employees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('company_id');
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }
    }

    // ==========================================
    // 1. AVAILABILITY MANAGEMENT TESTS
    // ==========================================

    public function test_interviewer_can_create_valid_availability_slot(): void
    {
        $interviewer = User::factory()->create();
        $start = Carbon::now()->addDays(2)->setTime(9, 0, 0);
        $end = $start->copy()->addHour();

        $response = $this->actingAs($interviewer)->postJson('/api/interviewer/availabilities', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.interviewer_id', $interviewer->id);
        $response->assertJsonPath('data.availability_status', 'open');
        $response->assertJsonPath('data.duration_minutes', 60);

        $this->assertDatabaseHas('interviewer_availability', [
            'interviewer_id' => $interviewer->id,
            'availability_status' => 'open',
        ]);
    }

    public function test_candidate_cannot_create_or_modify_interviewer_availability(): void
    {
        $candidateUser = User::factory()->create();
        DB::table('candidate_profiles')->insert([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->addDays(2)->setTime(9, 0, 0);
        $end = $start->copy()->addHour();

        $response = $this->actingAs($candidateUser)->postJson('/api/interviewer/availabilities', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
        ]);

        $response->assertStatus(403);
    }

    public function test_availability_rejects_past_time_and_invalid_duration(): void
    {
        $interviewer = User::factory()->create();

        // 1. Past start time
        $pastStart = Carbon::now()->subDays(1);
        $pastEnd = $pastStart->copy()->addHour();

        $respPast = $this->actingAs($interviewer)->postJson('/api/interviewer/availabilities', [
            'start_at' => $pastStart->toIso8601String(),
            'end_at' => $pastEnd->toIso8601String(),
        ]);
        $respPast->assertStatus(422);
        $respPast->assertJsonValidationErrors(['start_at']);

        // 2. End time before start time
        $futureStart = Carbon::now()->addDays(2)->setTime(10, 0, 0);
        $beforeEnd = $futureStart->copy()->subMinutes(30);

        $respInvalidEnd = $this->actingAs($interviewer)->postJson('/api/interviewer/availabilities', [
            'start_at' => $futureStart->toIso8601String(),
            'end_at' => $beforeEnd->toIso8601String(),
        ]);
        $respInvalidEnd->assertStatus(422);
        $respInvalidEnd->assertJsonValidationErrors(['end_at']);
    }

    public function test_availability_rejects_overlapping_slots_for_same_interviewer(): void
    {
        $interviewer = User::factory()->create();
        $start1 = Carbon::now()->addDays(2)->setTime(10, 0, 0);
        $end1 = $start1->copy()->addHours(2); // 10:00 to 12:00

        InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start1,
            'end_at' => $end1,
            'availability_status' => 'open',
        ]);

        // Overlapping slot: 11:00 to 13:00
        $start2 = Carbon::now()->addDays(2)->setTime(11, 0, 0);
        $end2 = $start2->copy()->addHours(2);

        $response = $this->actingAs($interviewer)->postJson('/api/interviewer/availabilities', [
            'start_at' => $start2->toIso8601String(),
            'end_at' => $end2->toIso8601String(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_at']);
    }

    public function test_hr_user_can_create_and_manage_availability_for_interviewer(): void
    {
        $hrUser = User::factory()->create(['role' => 'hr']);
        $interviewer = User::factory()->create();

        $start = Carbon::now()->addDays(3)->setTime(14, 0, 0);
        $end = $start->copy()->addHour();

        $response = $this->actingAs($hrUser)->postJson('/api/interviewer/availabilities', [
            'interviewer_id' => $interviewer->id,
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.interviewer_id', $interviewer->id);

        $slotId = $response->json('data.id');

        // HR can list and filter by interviewer_id
        $listResponse = $this->actingAs($hrUser)->getJson("/api/interviewer/availabilities?interviewer_id={$interviewer->id}");
        $listResponse->assertStatus(200);
        $this->assertNotEmpty($listResponse->json('data'));

        // HR can update the slot
        $newStart = Carbon::now()->addDays(4)->setTime(15, 0, 0);
        $newEnd = $newStart->copy()->addHour();

        $updateResponse = $this->actingAs($hrUser)->putJson("/api/interviewer/availabilities/{$slotId}", [
            'start_at' => $newStart->toIso8601String(),
            'end_at' => $newEnd->toIso8601String(),
        ]);
        $updateResponse->assertStatus(200);
    }

    public function test_booked_availability_slot_cannot_have_timings_modified_or_be_deleted(): void
    {
        $interviewer = User::factory()->create();
        $start = Carbon::now()->addDays(3)->setTime(10, 0, 0);
        $end = $start->copy()->addHour();

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        // 1. Attempting to update booked timings
        $updateResponse = $this->actingAs($interviewer)->putJson("/api/interviewer/availabilities/{$slot->id}", [
            'start_at' => $start->copy()->addHours(2)->toIso8601String(),
            'end_at' => $end->copy()->addHours(2)->toIso8601String(),
        ]);
        $updateResponse->assertStatus(422);
        $updateResponse->assertJsonValidationErrors(['availability_status']);

        // 2. Attempting to delete booked slot
        $deleteResponse = $this->actingAs($interviewer)->deleteJson("/api/interviewer/availabilities/{$slot->id}");
        $deleteResponse->assertStatus(409);
        $deleteResponse->assertJsonPath('message', 'Cannot delete an availability slot that is currently booked.');
    }

    public function test_interviewer_can_delete_unbooked_availability_slot(): void
    {
        $interviewer = User::factory()->create();
        $start = Carbon::now()->addDays(3)->setTime(10, 0, 0);
        $end = $start->copy()->addHour();

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'open',
        ]);

        $response = $this->actingAs($interviewer)->deleteJson("/api/interviewer/availabilities/{$slot->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('interviewer_availability', [
            'id' => $slot->id,
        ]);
    }

    public function test_interviewer_cannot_modify_or_delete_another_interviewers_slot(): void
    {
        $interviewerA = User::factory()->create();
        $interviewerB = User::factory()->create();

        $start = Carbon::now()->addDays(3)->setTime(10, 0, 0);
        $end = $start->copy()->addHour();

        $slotA = InterviewerAvailability::create([
            'interviewer_id' => $interviewerA->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'open',
        ]);

        // Interviewer B tries to update Interviewer A's slot
        $updateResp = $this->actingAs($interviewerB)->putJson("/api/interviewer/availabilities/{$slotA->id}", [
            'start_at' => $start->copy()->addHour()->toIso8601String(),
            'end_at' => $end->copy()->addHour()->toIso8601String(),
        ]);
        $updateResp->assertStatus(403);

        // Interviewer B tries to delete Interviewer A's slot
        $deleteResp = $this->actingAs($interviewerB)->deleteJson("/api/interviewer/availabilities/{$slotA->id}");
        $deleteResp->assertStatus(403);
    }

    // ==========================================
    // 2. CANDIDATE AVAILABLE SLOTS DISCOVERY
    // ==========================================

    public function test_candidate_can_view_eligible_future_available_slots(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'status' => 'interviewing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interviewer = User::factory()->create(['name' => 'Dr. Robert Specialist']);

        // 1. Eligible future open slot
        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2)->setTime(10, 0),
            'end_at' => Carbon::now()->addDays(2)->setTime(11, 0),
            'availability_status' => 'open',
        ]);

        // 2. Booked slot (should not appear)
        $slotBooked = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(2)->setTime(12, 0),
            'end_at' => Carbon::now()->addDays(2)->setTime(13, 0),
            'availability_status' => 'booked',
        ]);

        // 3. Past open slot (should not appear)
        $slotPast = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->subDays(1),
            'end_at' => Carbon::now()->subDays(1)->addHour(),
            'availability_status' => 'open',
        ]);

        $response = $this->actingAs($candidateUser)->getJson("/api/candidate/available-slots?application_id={$appId}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($slot1->id, $data[0]['id']);
        $this->assertEquals('Dr. Robert Specialist', $data[0]['interviewer']['name']);
    }

    public function test_candidate_cannot_view_slots_for_another_candidates_application(): void
    {
        $candidateUserA = User::factory()->create();
        $candidateUserB = User::factory()->create();

        $profileA = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUserA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appA = DB::table('applications')->insertGetId([
            'candidate_id' => $profileA,
            'status' => 'interviewing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Candidate B requests slots using Candidate A's application ID
        $response = $this->actingAs($candidateUserB)->getJson("/api/candidate/available-slots?application_id={$appA}");
        $response->assertStatus(403);
    }

    // ==========================================
    // 3. CANDIDATE INTERVIEW BOOKING
    // ==========================================

    public function test_candidate_can_book_available_interview_slot(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'status' => 'interviewing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interviewer = User::factory()->create();
        $start = Carbon::now()->addDays(3)->setTime(10, 0);
        $end = $start->copy()->addHour();

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'open',
        ]);

        $payload = [
            'application_id' => $appId,
            'availability_id' => $slot->id,
            'interview_type' => 'online',
            'meeting_link' => 'https://meet.google.com/test-book',
            'instructions' => 'Candidate requested focus on distributed systems.',
        ];

        $response = $this->actingAs($candidateUser)->postJson('/api/candidate/interviews/book', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.application_id', $appId);
        $response->assertJsonPath('data.round_no', 1);
        $response->assertJsonPath('data.status', 'scheduled');
        $response->assertJsonPath('data.booking_status', 'confirmed');

        // Slot status is now booked
        $this->assertEquals('booked', $slot->fresh()->availability_status);

        $this->assertDatabaseHas('interviews', [
            'application_id' => $appId,
            'status' => 'scheduled',
            'created_by' => $candidateUser->id,
        ]);
    }

    public function test_candidate_booking_returns_409_conflict_when_slot_is_no_longer_available(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'status' => 'interviewing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interviewer = User::factory()->create();
        $start = Carbon::now()->addDays(3)->setTime(10, 0);
        $end = $start->copy()->addHour();

        // Slot already booked by another process
        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $payload = [
            'application_id' => $appId,
            'availability_id' => $slot->id,
        ];

        $response = $this->actingAs($candidateUser)->postJson('/api/candidate/interviews/book', $payload);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'This slot is no longer available.');
    }

    public function test_candidate_booking_prevents_candidate_schedule_overlap(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $app1 = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $app2 = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = Carbon::now()->addDays(3)->setTime(10, 0);
        $end = $start->copy()->addHour();

        $interviewer1 = User::factory()->create();
        $interviewer2 = User::factory()->create();

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer1->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $existingInterview = Interview::create([
            'application_id' => $app1,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        InterviewBooking::create([
            'interview_id' => $existingInterview->id,
            'interviewer_id' => $interviewer1->id,
            'availability_id' => $slot1->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        // Candidate attempts to book an overlapping slot on application 2
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer2->id,
            'start_at' => $start->copy()->addMinutes(15),
            'end_at' => $end->copy()->addMinutes(15),
            'availability_status' => 'open',
        ]);

        $payload = [
            'application_id' => $app2,
            'availability_id' => $slot2->id,
        ];

        $response = $this->actingAs($candidateUser)->postJson('/api/candidate/interviews/book', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['application_id']);
    }

    // ==========================================
    // 4. TWENTY-FOUR-HOUR POLICY TESTS
    // ==========================================

    public function test_rescheduling_is_allowed_more_than_24_hours_in_advance(): void
    {
        $interviewer = User::factory()->create();
        $start = Carbon::now()->addHours(25);
        $end = $start->copy()->addHour();

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(4),
            'end_at' => Carbon::now()->addDays(4)->addHour(),
            'availability_status' => 'open',
        ]);

        $interview = Interview::create([
            'application_id' => 99,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/reschedule", [
            'booking_id' => $booking->id,
            'new_availability_id' => $slot2->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('rescheduled', $interview->fresh()->status);
    }

    public function test_rescheduling_is_allowed_exactly_24_hours_in_advance(): void
    {
        $now = Carbon::parse('2026-10-25 10:00:00');
        Carbon::setTestNow($now);

        $interviewer = User::factory()->create();
        $start = $now->copy()->addHours(24);
        $end = $start->copy()->addHour();

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(4),
            'end_at' => Carbon::now()->addDays(4)->addHour(),
            'availability_status' => 'open',
        ]);

        $interview = Interview::create([
            'application_id' => 99,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/reschedule", [
            'booking_id' => $booking->id,
            'new_availability_id' => $slot2->id,
        ]);

        Carbon::setTestNow();

        $response->assertStatus(200);
    }

    public function test_rescheduling_is_rejected_less_than_24_hours_in_advance(): void
    {
        $interviewer = User::factory()->create();
        // 23 hours in advance: must be rejected
        $start = Carbon::now()->addHours(23);
        $end = $start->copy()->addHour();

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(4),
            'end_at' => Carbon::now()->addDays(4)->addHour(),
            'availability_status' => 'open',
        ]);

        $interview = Interview::create([
            'application_id' => 99,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'scheduled',
        ]);
        $booking = InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => $slot1->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/reschedule", [
            'booking_id' => $booking->id,
            'new_availability_id' => $slot2->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['booking']);
    }

    public function test_cancellation_is_allowed_more_than_24_hours_in_advance(): void
    {
        $interviewer = User::factory()->create();
        $start = Carbon::now()->addHours(26);
        $end = $start->copy()->addHour();

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => 99,
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

        $response = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/cancel", [
            'reason' => 'Schedule conflict',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $interview->fresh()->status);
    }

    public function test_cancellation_is_allowed_exactly_24_hours_in_advance(): void
    {
        $now = Carbon::parse('2026-10-25 10:00:00');
        Carbon::setTestNow($now);

        $interviewer = User::factory()->create();
        $start = $now->copy()->addHours(24);
        $end = $start->copy()->addHour();

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => 99,
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

        $response = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/cancel", [
            'reason' => 'Schedule adjustment exactly 24 hours in advance',
        ]);

        Carbon::setTestNow();

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $interview->fresh()->status);
    }

    public function test_cancellation_is_rejected_less_than_24_hours_in_advance(): void
    {
        $interviewer = User::factory()->create();
        // 12 hours before scheduled start: rejected!
        $start = Carbon::now()->addHours(12);
        $end = $start->copy()->addHour();

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => 99,
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

        $response = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/cancel", [
            'reason' => 'Last minute cancellation',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['interview']);
    }

    public function test_candidate_can_reschedule_and_cancel_their_own_interview_with_24_hour_rule(): void
    {
        $candidateUser = User::factory()->create();
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $appId = DB::table('applications')->insertGetId([
            'candidate_id' => $profileId,
            'status' => 'interviewing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interviewer = User::factory()->create();
        $start = Carbon::now()->addHours(30);
        $end = $start->copy()->addHour();

        $slot1 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);
        $slot2 = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => Carbon::now()->addDays(4),
            'end_at' => Carbon::now()->addDays(4)->addHour(),
            'availability_status' => 'open',
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
            'availability_id' => $slot1->id,
            'start_at' => $start,
            'end_at' => $end,
            'booking_status' => 'confirmed',
        ]);

        // Candidate reschedules > 24 hours in advance -> succeeds
        $rescheduleResp = $this->actingAs($candidateUser)->postJson("/api/candidate/interviews/{$interview->id}/reschedule", [
            'new_availability_id' => $slot2->id,
        ]);
        $rescheduleResp->assertStatus(200);
        $this->assertEquals('rescheduled', $interview->fresh()->status);

        // Candidate cancels > 24 hours in advance -> succeeds
        $cancelResp = $this->actingAs($candidateUser)->postJson("/api/candidate/interviews/{$interview->id}/cancel", [
            'reason' => 'Candidate accepted another offer',
        ]);
        $cancelResp->assertStatus(200);
        $this->assertEquals('cancelled', $interview->fresh()->status);
    }

    // ==========================================
    // 5. EVALUATION AND CANDIDATE SCORING TESTS
    // ==========================================

    public function test_authorized_hr_user_can_submit_interview_evaluation(): void
    {
        $hrUser = User::factory()->create(['role' => 'hr']);
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

        $payload = [
            'numeric_score' => 95.0,
            'internal_notes' => 'HR review: candidate demonstrated exceptional cultural alignment.',
            'candidate_feedback' => 'Great performance on behavioral questions.',
        ];

        $response = $this->actingAs($hrUser)->postJson("/api/interviews/{$interview->id}/evaluations", $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.numeric_score', 95);
        $response->assertJsonPath('data.evaluator_id', $hrUser->id);

        $this->assertDatabaseHas('interview_evaluations', [
            'interview_id' => $interview->id,
            'evaluator_id' => $hrUser->id,
            'numeric_score' => 95.0,
        ]);
    }

    public function test_evaluation_submission_validates_score_scale_0_to_100(): void
    {
        $interviewer = User::factory()->create();
        $start = Carbon::now()->subDays(1);
        $end = $start->copy()->addHour();

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewer->id,
            'start_at' => $start,
            'end_at' => $end,
            'availability_status' => 'booked',
        ]);

        $interview = Interview::create([
            'application_id' => 1,
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

        // Score > 100 rejected
        $respOver = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/evaluations", [
            'numeric_score' => 105.0,
            'internal_notes' => 'Good notes here.',
        ]);
        $respOver->assertStatus(422);
        $respOver->assertJsonValidationErrors(['numeric_score']);

        // Score < 0 rejected
        $respUnder = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/evaluations", [
            'numeric_score' => -5.0,
            'internal_notes' => 'Good notes here.',
        ]);
        $respUnder->assertStatus(422);
        $respUnder->assertJsonValidationErrors(['numeric_score']);
    }

    public function test_duplicate_evaluation_updates_existing_record_for_same_evaluator(): void
    {
        $interviewer = User::factory()->create();
        $interview = Interview::create([
            'application_id' => 1,
            'round_no' => 1,
            'interview_type' => 'online',
            'status' => 'completed',
        ]);
        InterviewBooking::create([
            'interview_id' => $interview->id,
            'interviewer_id' => $interviewer->id,
            'availability_id' => 1,
            'start_at' => Carbon::now()->subDays(1),
            'end_at' => Carbon::now()->subDays(1)->addHour(),
            'booking_status' => 'confirmed',
        ]);

        // Initial evaluation
        $resp1 = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/evaluations", [
            'numeric_score' => 75.0,
            'internal_notes' => 'Initial assessment.',
        ]);
        $resp1->assertStatus(201);
        $evalId = $resp1->json('data.id');

        // Second submission updates the existing evaluation record
        $resp2 = $this->actingAs($interviewer)->postJson("/api/interviews/{$interview->id}/evaluations", [
            'numeric_score' => 82.0,
            'internal_notes' => 'Updated assessment after reflection.',
        ]);
        $resp2->assertStatus(201);
        $this->assertEquals($evalId, $resp2->json('data.id'));

        $this->assertDatabaseCount('interview_evaluations', 1);
        $this->assertDatabaseHas('interview_evaluations', [
            'id' => $evalId,
            'numeric_score' => 82.0,
            'internal_notes' => 'Updated assessment after reflection.',
        ]);
    }
}
