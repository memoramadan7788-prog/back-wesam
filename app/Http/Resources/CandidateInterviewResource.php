<?php

namespace App\Http\Resources;

use App\Models\Interview;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Candidate-facing Interview Resource for Candidate Interview Page.
 * Strictly complies with ERD Page 4 data protection:
 * - numeric_score and internal_notes are NEVER exposed to candidates unless officially published.
 * - candidate_feedback is exposed ONLY when feedback_shared_at is set.
 * - Exposes all data required by Candidate Interview Page:
 *   round_no, interview_type (online/onsite), booking dates/times,
 *   interview & booking status, location, meeting_link, application rounds timeline,
 *   application/job context, safe instructions handling, and officially shared feedback.
 *
 * @mixin \App\Models\Interview
 */
class CandidateInterviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $evaluations = $this->relationLoaded('evaluations') ? $this->evaluations : collect();

        // Find latest officially published evaluation (feedback_shared_at is not null)
        $latestPublished = $evaluations
            ->filter(fn($ev) => $ev->feedback_shared_at !== null)
            ->sortByDesc('feedback_shared_at')
            ->first();

        // Derive evaluation status: pending, submitted, published
        $evaluationStatus = 'pending';
        if ($evaluations->isNotEmpty()) {
            $evaluationStatus = $latestPublished ? 'published' : 'submitted';
        }

        // Shared feedback list for candidate display
        $sharedFeedback = $evaluations
            ->filter(fn($ev) => $ev->feedback_shared_at !== null)
            ->map(fn($ev) => [
                'id' => $ev->id,
                'numeric_score' => $ev->numeric_score !== null ? (float) $ev->numeric_score : null,
                'candidate_feedback' => $ev->candidate_feedback,
                'feedback' => $ev->candidate_feedback, // backwards compatibility
                'feedback_shared_at' => $ev->feedback_shared_at?->toIso8601String(),
                'shared_at' => $ev->feedback_shared_at?->toIso8601String(), // backwards compatibility
            ])
            ->values();

        // Resolve active confirmed booking or latest booking for top-level display
        $bookings = $this->relationLoaded('bookings') ? $this->bookings : collect();
        $activeBooking = $bookings->firstWhere('booking_status', 'confirmed')
            ?? $bookings->firstWhere('booking_status', '!=', 'cancelled')
            ?? $bookings->first();

        // Resolve application & job context via Query Builder if not preloaded
        $applicationData = $this->applicationData ?? (
            ($this->application_id && Schema::hasTable('applications')) ? DB::table('applications')
                ->when(Schema::hasTable('jobs'), fn($q) => $q->leftJoin('jobs', 'jobs.id', '=', 'applications.job_id'))
                ->when(Schema::hasTable('companies') && Schema::hasTable('jobs'), fn($q) => $q->leftJoin('companies', 'companies.id', '=', 'jobs.company_id'))
                ->where('applications.id', $this->application_id)
                ->select([
                    'applications.id as application_id',
                    (Schema::hasColumn('applications', 'status')) ? 'applications.status as application_status' : DB::raw('NULL as application_status'),
                    (Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'title')) ? 'jobs.title as job_title' : DB::raw('NULL as job_title'),
                    (Schema::hasTable('companies') && Schema::hasColumn('companies', 'name')) ? 'companies.name as company_name' : DB::raw('NULL as company_name'),
                ])
                ->first() : null
        );

        // Resolve application interview rounds timeline
        $applicationRounds = $this->applicationRounds ?? (
            $this->application_id
                ? Interview::query()
                    ->where('application_id', $this->application_id)
                    ->with('bookings')
                    ->orderBy('round_no', 'asc')
                    ->orderBy('id', 'asc')
                    ->get()
                : collect([$this->resource])
        );

        $type = (string) $this->interview_type;
        $isOnline = in_array(strtolower(trim($type)), ['online', 'remote', 'video']);

        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'round_no' => $this->round_no,
            'interview_type' => $this->interview_type,
            'is_online' => $isOnline,
            'status' => $this->status,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            // Primary schedule dates and times for candidate display
            'start_at' => $activeBooking?->start_at?->toIso8601String(),
            'end_at' => $activeBooking?->end_at?->toIso8601String(),
            'booking_status' => $activeBooking?->booking_status,
            // Safe handling of instructions (returns null if column not present in schema)
            'instructions' => (Schema::hasTable('interviews') && Schema::hasColumn('interviews', 'instructions'))
                ? $this->instructions
                : null,
            // Derived candidate evaluation status
            'evaluation_status' => $evaluationStatus,
            'numeric_score' => $this->when($latestPublished !== null, fn() => $latestPublished->numeric_score !== null ? (float) $latestPublished->numeric_score : null),
            'candidate_feedback' => $this->when($latestPublished !== null, fn() => $latestPublished->candidate_feedback),
            'feedback_shared_at' => $this->when($latestPublished !== null, fn() => $latestPublished->feedback_shared_at?->toIso8601String()),
            'shared_feedback' => $this->when($sharedFeedback->isNotEmpty(), $sharedFeedback),
            // Associated application and job context
            'application' => $applicationData ? [
                'id' => $applicationData->application_id,
                'status' => $applicationData->application_status,
                'job_title' => $applicationData->job_title,
                'company_name' => $applicationData->company_name,
            ] : null,
            // Application interview rounds ordered timeline
            'rounds' => $this->formatRoundsTimeline($applicationRounds),
            // Detailed bookings list
            'bookings' => $this->whenLoaded('bookings', function () {
                return $this->bookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'start_at' => $booking->start_at?->toIso8601String(),
                        'end_at' => $booking->end_at?->toIso8601String(),
                        'booking_status' => $booking->booking_status,
                        'status' => $booking->booking_status, // backwards compatibility
                    ];
                })->values();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format application interview rounds into a chronological timeline with reliable round state.
     * Accurately distinguishes:
     * - An interview happening right now ('in_progress')
     * - Future scheduled rounds ('upcoming')
     * - Current workflow stage awaiting scheduling ('current')
     * - Completed / ended rounds ('past')
     * - Cancelled rounds ('cancelled')
     * Also exposes `is_current_stage` to identify the active application stage independently of scheduling timing.
     */
    protected function formatRoundsTimeline($rounds): array
    {
        if ($rounds->isEmpty()) {
            return [];
        }

        $sorted = $rounds->sortBy(fn($r) => [$r->round_no, $r->id])->values();
        $now = Carbon::now();

        // Step 1: Identify current workflow stage index (earliest non-cancelled, non-past round)
        $currentStageIndex = null;
        foreach ($sorted as $index => $round) {
            $bookings = $round->relationLoaded('bookings') ? $round->bookings : collect();
            $activeBooking = $bookings->firstWhere('booking_status', 'confirmed')
                ?? $bookings->firstWhere('booking_status', '!=', 'cancelled')
                ?? $bookings->first();

            $isCancelled = strtolower((string) $round->status) === 'cancelled';
            $isCompleted = strtolower((string) $round->status) === 'completed';
            $isPastBooking = $activeBooking && $activeBooking->end_at && Carbon::parse($activeBooking->end_at)->isPast();

            if (!$isCancelled && !$isCompleted && !$isPastBooking) {
                $currentStageIndex = $index;
                break;
            }
        }

        // Step 2: Determine round_state and flags for each round
        return $sorted->map(function ($round, $index) use ($currentStageIndex, $now) {
            $bookings = $round->relationLoaded('bookings') ? $round->bookings : collect();
            $activeBooking = $bookings->firstWhere('booking_status', 'confirmed')
                ?? $bookings->firstWhere('booking_status', '!=', 'cancelled')
                ?? $bookings->first();

            $status = strtolower((string) $round->status);
            $startAt = $activeBooking?->start_at ? Carbon::parse($activeBooking->start_at) : null;
            $endAt = $activeBooking?->end_at ? Carbon::parse($activeBooking->end_at) : null;

            if ($status === 'cancelled') {
                $roundState = 'cancelled';
            } elseif ($status === 'completed' || ($endAt && $endAt->isPast())) {
                $roundState = 'past';
            } elseif ($startAt && $endAt && $now->between($startAt, $endAt)) {
                // The interview is actively in progress right now
                $roundState = 'in_progress';
            } elseif ($startAt && $startAt->isFuture()) {
                // Scheduled in the future: always 'upcoming', never 'current'
                $roundState = 'upcoming';
            } elseif ($index === $currentStageIndex) {
                // Unscheduled round that is the current workflow stage
                $roundState = 'current';
            } else {
                $roundState = 'upcoming';
            }

            $type = (string) $round->interview_type;
            $isOnline = in_array(strtolower(trim($type)), ['online', 'remote', 'video']);

            return [
                'id' => $round->id,
                'round_no' => $round->round_no,
                'interview_type' => $type,
                'is_online' => $isOnline,
                'status' => $round->status,
                'location' => $round->location,
                'meeting_link' => $round->meeting_link,
                'start_at' => $activeBooking?->start_at?->toIso8601String(),
                'end_at' => $activeBooking?->end_at?->toIso8601String(),
                'booking_status' => $activeBooking?->booking_status,
                'is_current' => $round->id === $this->id,
                'is_current_stage' => $index === $currentStageIndex,
                'round_state' => $roundState,
            ];
        })->values()->all();
    }
}

