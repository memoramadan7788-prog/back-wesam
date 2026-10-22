<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInterviewRequest;
use App\Http\Requests\RescheduleInterviewRequest;
use App\Http\Requests\ScheduleInterviewRequest;
use App\Http\Resources\InterviewerInterviewResource;
use App\Models\Interview;
use App\Services\InterviewSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class InterviewSchedulingController extends Controller
{
    /**
     * Schedule a new interview.
     */
    public function store(ScheduleInterviewRequest $request, InterviewSchedulingService $service): JsonResponse
    {
        Gate::authorize('schedule', Interview::class);

        $interview = $service->schedule(
            $request->validated(),
            $request->user()->id
        );

        return (new InterviewerInterviewResource($interview))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Reschedule an existing interview booking.
     */
    public function reschedule(RescheduleInterviewRequest $request, int $id, InterviewSchedulingService $service): InterviewerInterviewResource
    {
        $interview = Interview::query()->with('bookings')->findOrFail($id);

        Gate::authorize('reschedule', $interview);

        $booking = $interview->bookings()
            ->where('id', $request->validated('booking_id'))
            ->firstOrFail();

        $service->rescheduleBooking($booking, $request->validated('new_availability_id'));

        return new InterviewerInterviewResource($interview->fresh(['bookings.availability', 'bookings.interviewer']));
    }

    /**
     * Cancel an interview.
     */
    public function cancel(CancelInterviewRequest $request, int $id, InterviewSchedulingService $service): InterviewerInterviewResource
    {
        $interview = Interview::query()->with('bookings')->findOrFail($id);

        Gate::authorize('cancel', $interview);

        $cancelledInterview = $service->cancelInterview(
            $interview,
            $request->user()->id,
            $request->validated('reason')
        );

        return new InterviewerInterviewResource($cancelledInterview);
    }
}
