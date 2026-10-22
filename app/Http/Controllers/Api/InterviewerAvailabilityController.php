<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvailabilityRequest;
use App\Http\Requests\UpdateAvailabilityRequest;
use App\Http\Resources\InterviewerAvailabilityResource;
use App\Models\InterviewerAvailability;
use App\Policies\InterviewerAvailabilityPolicy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InterviewerAvailabilityController extends Controller
{
    /**
     * List interviewer availability slots.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InterviewerAvailability::class);

        $user = $request->user();
        $policy = app(InterviewerAvailabilityPolicy::class);
        $isRecruiter = $policy->hasRecruitmentPrivilege($user);

        $query = InterviewerAvailability::query()->with('interviewer');

        if ($isRecruiter) {
            if ($interviewerId = $request->input('interviewer_id')) {
                $query->where('interviewer_id', $interviewerId);
            }
        } else {
            $query->where('interviewer_id', $user->id);
        }

        if ($status = $request->input('status')) {
            $query->where('availability_status', $status);
        }

        if ($from = $request->input('from')) {
            $query->where('start_at', '>=', Carbon::parse($from));
        }

        if ($to = $request->input('to')) {
            $query->where('start_at', '<=', Carbon::parse($to));
        }

        $perPage = (int) $request->input('per_page', 15);
        $slots = $query->orderBy('start_at', 'asc')->paginate($perPage);

        return InterviewerAvailabilityResource::collection($slots);
    }

    /**
     * Create an availability period or time slot.
     */
    public function store(StoreAvailabilityRequest $request): JsonResponse
    {
        Gate::authorize('create', InterviewerAvailability::class);

        $user = $request->user();
        $policy = app(InterviewerAvailabilityPolicy::class);
        $isRecruiter = $policy->hasRecruitmentPrivilege($user);

        $interviewerId = ($isRecruiter && $request->filled('interviewer_id'))
            ? (int) $request->input('interviewer_id')
            : $user->id;

        $slot = InterviewerAvailability::create([
            'interviewer_id' => $interviewerId,
            'start_at' => Carbon::parse($request->validated('start_at')),
            'end_at' => Carbon::parse($request->validated('end_at')),
            'availability_status' => 'open',
        ]);

        return (new InterviewerAvailabilityResource($slot->load('interviewer')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * View a specific availability slot.
     */
    public function show(Request $request, int $id): InterviewerAvailabilityResource
    {
        $slot = InterviewerAvailability::query()->with('interviewer')->findOrFail($id);

        Gate::authorize('view', $slot);

        return new InterviewerAvailabilityResource($slot);
    }

    /**
     * Update an availability slot.
     * Prevents modifying booked slots to protect confirmed interviews.
     */
    public function update(UpdateAvailabilityRequest $request, int $id): InterviewerAvailabilityResource
    {
        $slot = InterviewerAvailability::query()->with('interviewer')->findOrFail($id);

        Gate::authorize('update', $slot);

        $data = [];
        if ($request->has('start_at')) {
            $data['start_at'] = Carbon::parse($request->validated('start_at'));
        }
        if ($request->has('end_at')) {
            $data['end_at'] = Carbon::parse($request->validated('end_at'));
        }
        if ($request->has('availability_status')) {
            $data['availability_status'] = $request->validated('availability_status');
        }

        $slot->update($data);

        return new InterviewerAvailabilityResource($slot->fresh('interviewer'));
    }

    /**
     * Delete an availability slot if not booked.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $slot = InterviewerAvailability::query()->findOrFail($id);

        Gate::authorize('delete', $slot);

        $hasActiveBookings = $slot->bookings()
            ->where('booking_status', '!=', 'cancelled')
            ->exists();

        if (strtolower((string) $slot->availability_status) === 'booked' || $hasActiveBookings) {
            return response()->json([
                'message' => 'Cannot delete an availability slot that is currently booked.',
            ], 409);
        }

        $slot->forceDelete();

        return response()->json([
            'message' => 'Availability slot deleted successfully.',
        ], 200);
    }
}
