<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InterviewerAvailabilityResource;
use App\Models\InterviewerAvailability;
use App\Services\InterviewSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CandidateAvailableSlotsController extends Controller
{
    /**
     * Display available interview slots to an authenticated candidate.
     */
    public function index(Request $request, InterviewSchedulingService $schedulingService): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        // Verify eligibility on specified application if provided
        $applicationId = $request->input('application_id');
        if ($applicationId && Schema::hasTable('applications') && Schema::hasTable('candidate_profiles')) {
            $application = DB::table('applications')
                ->join('candidate_profiles', 'candidate_profiles.id', '=', 'applications.candidate_id')
                ->where('applications.id', $applicationId)
                ->where('candidate_profiles.user_id', $userId)
                ->select(['applications.id', 'applications.status'])
                ->first();

            if (!$application) {
                abort(403, 'You are not authorized to access interview slots for this application.');
            }

            if (in_array(strtolower((string) $application->status), ['rejected', 'withdrawn', 'cancelled'], true)) {
                abort(403, 'The specified application is no longer active for interview scheduling.');
            }
        }

        $now = Carbon::now();

        $query = InterviewerAvailability::query()
            ->with('interviewer')
            ->where('availability_status', 'open')
            ->where('start_at', '>', $now);

        if ($interviewerId = $request->input('interviewer_id')) {
            $query->where('interviewer_id', $interviewerId);
        }

        if ($from = $request->input('from')) {
            $query->where('start_at', '>=', Carbon::parse($from));
        }

        if ($to = $request->input('to')) {
            $query->where('start_at', '<=', Carbon::parse($to));
        }

        // Company filter if company_employees exists
        if ($companyId = $request->input('company_id')) {
            if (Schema::hasTable('company_employees')) {
                $query->whereIn('interviewer_id', function ($sub) use ($companyId) {
                    $sub->select('user_id')
                        ->from('company_employees')
                        ->where('company_id', $companyId);
                });
            }
        }

        $perPage = (int) $request->input('per_page', 15);
        $slots = $query->orderBy('start_at', 'asc')->paginate($perPage);

        // Filter out slots that conflict with the candidate's existing schedule if application_id is provided
        if ($applicationId) {
            $filteredItems = $slots->getCollection()->filter(function ($slot) use ($schedulingService, $applicationId) {
                return !$schedulingService->hasCandidateBookingConflict($applicationId, $slot->start_at, $slot->end_at);
            })->values();

            $slots->setCollection($filteredItems);
        }

        return InterviewerAvailabilityResource::collection($slots)->additional([
            'meta_filters' => [
                'application_id' => $applicationId,
                'interviewer_id' => $interviewerId ?? null,
                'from' => $from ?? null,
                'to' => $to ?? null,
            ],
        ]);
    }
}
