<?php

namespace App\Services;

use App\Enums\InterviewType;
use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewerAvailability;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InterviewSchedulingService
{
    /**
     * Schedule a new interview and create interviewer bookings.
     * Enforces database transactions, row locking, and slot availability checks.
     *
     * @param array<string, mixed> $data
     * @param int|string $createdBy
     * @return Interview
     * @throws ValidationException
     */
    public function schedule(array $data, int|string $createdBy): Interview
    {
        $applicationId = $data['application_id'];
        $roundNo = (int) ($data['round_no'] ?? 1);
        $interviewType = $data['interview_type'] ?? 'online';
        $location = $data['location'] ?? null;
        $meetingLink = $data['meeting_link'] ?? null;
        $instructions = $data['instructions'] ?? null;
        $bookingsData = $data['bookings'] ?? [];

        // Normalize 'onsite' compatibility alias to canonical enum value
        if (is_string($interviewType) && strtolower(trim($interviewType)) === 'onsite') {
            $interviewType = InterviewType::ON_SITE->value;
        }

        if (!in_array($interviewType, InterviewType::values(), true)) {
            throw ValidationException::withMessages([
                'interview_type' => ["The selected interview type '{$interviewType}' is invalid."],
            ]);
        }

        if (empty($bookingsData)) {
            throw ValidationException::withMessages([
                'bookings' => ['At least one interviewer booking is required to schedule an interview.'],
            ]);
        }

        return DB::transaction(function () use ($applicationId, $roundNo, $interviewType, $location, $meetingLink, $instructions, $createdBy, $bookingsData) {
            $lockedSlots = [];

            // 1. Lock and validate all requested interviewer availability slots
            foreach ($bookingsData as $index => $bookingItem) {
                $interviewerId = $bookingItem['interviewer_id'];
                $availabilityId = $bookingItem['availability_id'];

                /** @var InterviewerAvailability|null $slot */
                $slot = InterviewerAvailability::query()
                    ->where('id', $availabilityId)
                    ->lockForUpdate()
                    ->first();

                if (!$slot) {
                    throw ValidationException::withMessages([
                        "bookings.{$index}.availability_id" => ["Availability slot #{$availabilityId} not found."],
                    ]);
                }

                if ((int) $slot->interviewer_id !== (int) $interviewerId) {
                    throw ValidationException::withMessages([
                        "bookings.{$index}.availability_id" => ["Availability slot #{$availabilityId} does not belong to interviewer #{$interviewerId}."],
                    ]);
                }

                if (strtolower((string) $slot->availability_status) !== 'open') {
                    throw ValidationException::withMessages([
                        "bookings.{$index}.availability_id" => ["Availability slot #{$availabilityId} is not available (status: {$slot->availability_status})."],
                    ]);
                }

                // Check for candidate scheduling conflicts during this slot window
                if ($this->hasCandidateBookingConflict($applicationId, $slot->start_at, $slot->end_at)) {
                    throw ValidationException::withMessages([
                        'application_id' => ['The candidate already has an active interview scheduled during this time slot.'],
                    ]);
                }

                $lockedSlots[] = [
                    'interviewer_id' => $interviewerId,
                    'slot' => $slot,
                ];
            }

            // 2. Create the Interview parent entity (ERD Page 4)
            $interviewAttributes = [
                'application_id' => $applicationId,
                'round_no' => $roundNo,
                'interview_type' => $interviewType,
                'status' => 'scheduled',
                'location' => $location,
                'meeting_link' => $meetingLink,
                'created_by' => $createdBy,
            ];

            if (!Schema::hasTable('interviews') || Schema::hasColumn('interviews', 'instructions')) {
                $interviewAttributes['instructions'] = $instructions;
            }

            $interview = Interview::create($interviewAttributes);

            // 3. Create interview bookings and reserve the availability slots
            foreach ($lockedSlots as $item) {
                /** @var InterviewerAvailability $slot */
                $slot = $item['slot'];

                InterviewBooking::create([
                    'interview_id' => $interview->id,
                    'interviewer_id' => $item['interviewer_id'],
                    'availability_id' => $slot->id,
                    'start_at' => $slot->start_at,
                    'end_at' => $slot->end_at,
                    'booking_status' => 'confirmed',
                ]);

                $slot->update([
                    'availability_status' => 'booked',
                ]);
            }

            return $interview->load(['bookings.interviewer', 'bookings.availability']);
        });
    }

    /**
     * Reschedule an existing interview booking to a new availability slot.
     * Within the same transaction: reserves the new slot successfully before releasing the old slot.
     *
     * @param InterviewBooking $booking
     * @param int|string $newAvailabilityId
     * @return InterviewBooking
     * @throws ValidationException
     */
    public function rescheduleBooking(InterviewBooking $booking, int|string $newAvailabilityId): InterviewBooking
    {
        if (strtolower((string) $booking->booking_status) === 'cancelled') {
            throw ValidationException::withMessages([
                'booking' => ['Cancelled bookings cannot be rescheduled.'],
            ]);
        }

        // 24-Hour Modification Policy: Changes must be at least 24 hours before scheduled start time
        if ($booking->start_at) {
            $now = Carbon::now();
            $scheduledStart = Carbon::parse($booking->start_at);
            if ($now->diffInSeconds($scheduledStart, false) < 24 * 3600) {
                throw ValidationException::withMessages([
                    'booking' => ['Interviews can only be rescheduled at least 24 hours before the scheduled start time.'],
                ]);
            }
        }

        return DB::transaction(function () use ($booking, $newAvailabilityId) {
            // Lock and validate the new availability slot first
            /** @var InterviewerAvailability|null $newSlot */
            $newSlot = InterviewerAvailability::query()
                ->where('id', $newAvailabilityId)
                ->lockForUpdate()
                ->first();

            if (!$newSlot) {
                throw ValidationException::withMessages([
                    'new_availability_id' => ["New availability slot #{$newAvailabilityId} not found."],
                ]);
            }

            if ((int) $newSlot->interviewer_id !== (int) $booking->interviewer_id) {
                throw ValidationException::withMessages([
                    'new_availability_id' => ["New availability slot #{$newAvailabilityId} does not belong to this interviewer."],
                ]);
            }

            if (strtolower((string) $newSlot->availability_status) !== 'open') {
                throw ValidationException::withMessages([
                    'new_availability_id' => ["New availability slot #{$newAvailabilityId} is not available."],
                ]);
            }

            // Check candidate conflict on the new slot (excluding this interview booking)
            $applicationId = $booking->interview->application_id;
            if ($this->hasCandidateBookingConflict($applicationId, $newSlot->start_at, $newSlot->end_at, $booking->id)) {
                throw ValidationException::withMessages([
                    'new_availability_id' => ['The candidate has a scheduling conflict during the proposed new time slot.'],
                ]);
            }

            $oldAvailabilityId = $booking->availability_id;

            // Reserve the new slot
            $newSlot->update([
                'availability_status' => 'booked',
            ]);

            // Update booking details
            $booking->update([
                'availability_id' => $newSlot->id,
                'start_at' => $newSlot->start_at,
                'end_at' => $newSlot->end_at,
                'booking_status' => 'confirmed',
            ]);

            // Update parent interview status
            $booking->interview->update([
                'status' => 'rescheduled',
            ]);

            // Safely release the old slot only if no other active booking occupies it
            $this->releaseAvailabilitySlotIfUnoccupied($oldAvailabilityId);

            return $booking->fresh(['availability', 'interviewer', 'interview']);
        });
    }

    /**
     * Cancel an interview and its bookings.
     * Releases availability slots within the transaction only if unoccupied by other active bookings.
     * Enforces the 24-hour cancellation policy.
     *
     * @param Interview $interview
     * @param int|string $cancelledBy
     * @param string|null $reason
     * @return Interview
     * @throws ValidationException
     */
    public function cancelInterview(Interview $interview, int|string $cancelledBy, ?string $reason = null): Interview
    {
        if (!$interview->canBeCancelled()) {
            throw ValidationException::withMessages([
                'interview' => ['The interview cannot be cancelled in its current status.'],
            ]);
        }

        // 24-Hour Cancellation Policy: Cancellations must be at least 24 hours before scheduled start time
        $activeBooking = $interview->bookings()
            ->where('booking_status', '!=', 'cancelled')
            ->orderBy('start_at', 'asc')
            ->first();

        if ($activeBooking && $activeBooking->start_at) {
            $now = Carbon::now();
            $scheduledStart = Carbon::parse($activeBooking->start_at);
            if ($now->diffInSeconds($scheduledStart, false) < 24 * 3600) {
                throw ValidationException::withMessages([
                    'interview' => ['Interviews can only be cancelled at least 24 hours before the scheduled start time.'],
                ]);
            }
        }

        return DB::transaction(function () use ($interview, $cancelledBy) {
            $interview->update([
                'status' => 'cancelled',
            ]);

            foreach ($interview->bookings as $booking) {
                if (strtolower((string) $booking->booking_status) !== 'cancelled') {
                    $availabilityId = $booking->availability_id;

                    $booking->update([
                        'booking_status' => 'cancelled',
                        'cancelled_by' => $cancelledBy,
                        'cancelled_at' => Carbon::now(),
                    ]);

                    // Release availability slot only if not occupied by another active booking
                    $this->releaseAvailabilitySlotIfUnoccupied($availabilityId);
                }
            }

            return $interview->fresh(['bookings.availability', 'bookings.interviewer']);
        });
    }

    /**
     * Book an interview slot directly by an authenticated candidate.
     * Enforces concurrency row-level locking (409 Conflict if taken), candidate ownership,
     * overlap conflict prevention, and atomic interview creation.
     *
     * @param array<string, mixed> $data
     * @param int|string $candidateUserId
     * @return Interview
     * @throws ValidationException
     */
    public function bookCandidateSlot(array $data, int|string $candidateUserId): Interview
    {
        $applicationId = (int) $data['application_id'];
        $availabilityId = (int) $data['availability_id'];
        $interviewType = $data['interview_type'] ?? 'online';
        $location = $data['location'] ?? null;
        $meetingLink = $data['meeting_link'] ?? null;
        $instructions = $data['instructions'] ?? null;

        // Verify candidate ownership of application
        if (Schema::hasTable('applications') && Schema::hasTable('candidate_profiles')) {
            $ownsApplication = DB::table('applications')
                ->join('candidate_profiles', 'candidate_profiles.id', '=', 'applications.candidate_id')
                ->where('applications.id', $applicationId)
                ->where('candidate_profiles.user_id', $candidateUserId)
                ->exists();

            if (!$ownsApplication) {
                throw ValidationException::withMessages([
                    'application_id' => ['You are not authorized to book an interview for this application.'],
                ]);
            }
        }

        // Normalize interview type
        if (is_string($interviewType) && strtolower(trim($interviewType)) === 'onsite') {
            $interviewType = InterviewType::ON_SITE->value;
        }

        if (!in_array($interviewType, InterviewType::values(), true)) {
            throw ValidationException::withMessages([
                'interview_type' => ["The selected interview type '{$interviewType}' is invalid."],
            ]);
        }

        return DB::transaction(function () use ($applicationId, $availabilityId, $interviewType, $location, $meetingLink, $instructions, $candidateUserId) {
            // Lock slot with row-level locking to handle concurrent booking requests safely
            /** @var InterviewerAvailability|null $slot */
            $slot = InterviewerAvailability::query()
                ->where('id', $availabilityId)
                ->lockForUpdate()
                ->first();

            if (!$slot || strtolower((string) $slot->availability_status) !== 'open') {
                abort(409, 'This slot is no longer available.');
            }

            if ($slot->start_at && $slot->start_at->isPast()) {
                throw ValidationException::withMessages([
                    'availability_id' => ['Cannot book an availability slot in the past.'],
                ]);
            }

            // Check candidate scheduling conflicts
            if ($this->hasCandidateBookingConflict($applicationId, $slot->start_at, $slot->end_at)) {
                throw ValidationException::withMessages([
                    'application_id' => ['The candidate already has an active interview scheduled during this time slot.'],
                ]);
            }

            // Determine round_no
            $maxRound = Interview::where('application_id', $applicationId)->max('round_no');
            $roundNo = ($maxRound !== null) ? (int) $maxRound + 1 : 1;

            $interviewAttributes = [
                'application_id' => $applicationId,
                'round_no' => $roundNo,
                'interview_type' => $interviewType,
                'status' => 'scheduled',
                'location' => $location,
                'meeting_link' => $meetingLink,
                'created_by' => $candidateUserId,
            ];

            if (!Schema::hasTable('interviews') || Schema::hasColumn('interviews', 'instructions')) {
                $interviewAttributes['instructions'] = $instructions;
            }

            $interview = Interview::create($interviewAttributes);

            InterviewBooking::create([
                'interview_id' => $interview->id,
                'interviewer_id' => $slot->interviewer_id,
                'availability_id' => $slot->id,
                'start_at' => $slot->start_at,
                'end_at' => $slot->end_at,
                'booking_status' => 'confirmed',
            ]);

            $slot->update([
                'availability_status' => 'booked',
            ]);

            return $interview->load(['bookings.interviewer', 'bookings.availability']);
        });
    }

    /**
     * Check whether a candidate has an active overlapping booking across any of their applications.
     * Traverses the confirmed ERD relationship chain:
     * applications.candidate_id -> candidate_profiles.id -> candidate_profiles.user_id
     */
    public function hasCandidateBookingConflict(int|string $applicationId, Carbon $start, Carbon $end, ?int $excludeBookingId = null): bool
    {
        return InterviewBooking::query()
            ->whereHas('interview', function (Builder $q) use ($applicationId) {
                $q->where('status', '!=', 'cancelled');

                if (Schema::hasTable('applications')) {
                    $q->whereIn('application_id', function ($sub) use ($applicationId) {
                        $sub->select('a2.id')
                            ->from('applications as a1');

                        if (Schema::hasTable('candidate_profiles')) {
                            $sub->join('candidate_profiles as cp1', 'cp1.id', '=', 'a1.candidate_id')
                                ->join('candidate_profiles as cp2', 'cp2.user_id', '=', 'cp1.user_id')
                                ->join('applications as a2', 'a2.candidate_id', '=', 'cp2.id');
                        } else {
                            $sub->join('applications as a2', 'a2.candidate_id', '=', 'a1.candidate_id');
                        }

                        $sub->where('a1.id', $applicationId);
                    });
                } else {
                    $q->where('application_id', $applicationId);
                }
            })
            ->where('booking_status', '!=', 'cancelled')
            ->when($excludeBookingId, fn(Builder $q) => $q->where('id', '!=', $excludeBookingId))
            ->where(function (Builder $q) use ($start, $end) {
                // Interval overlap: existing start < new end AND existing end > new start
                $q->where('start_at', '<', $end)
                  ->where('end_at', '>', $start);
            })
            ->exists();
    }

    /**
     * Safely release an availability slot back to 'open' only if no other active booking occupies it.
     */
    protected function releaseAvailabilitySlotIfUnoccupied(int|string $availabilityId): void
    {
        $hasActiveBookings = InterviewBooking::query()
            ->where('availability_id', $availabilityId)
            ->where('booking_status', '!=', 'cancelled')
            ->exists();

        if (!$hasActiveBookings) {
            InterviewerAvailability::query()
                ->where('id', $availabilityId)
                ->update(['availability_status' => 'open']);
        }
    }
}
