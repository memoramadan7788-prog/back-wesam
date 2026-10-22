<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CandidateInterviewFilterRequest;
use App\Http\Resources\CandidateInterviewResource;
use App\Models\Interview;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class CandidateInterviewController extends Controller
{
    /**
     * List all interviews for the authenticated candidate.
     */
    public function index(CandidateInterviewFilterRequest $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        $query = Interview::query()
            ->with(['bookings', 'evaluations'])
            ->forCandidate($userId);

        // Apply filter presets (all | upcoming | past)
        $filter = $request->input('filter', 'all');
        if ($filter === 'upcoming') {
            $query->upcoming();
        } elseif ($filter === 'past') {
            $query->past();
        }

        // Apply status filter
        if ($status = $request->input('status')) {
            $query->filterByStatus($status);
        }

        // Apply date range filter
        $from = $request->input('from');
        if ($from) {
            $query->whereHas('bookings', fn($q) => $q->where('start_at', '>=', Carbon::parse($from)));
        }

        $to = $request->input('to');
        if ($to) {
            $query->whereHas('bookings', fn($q) => $q->where('start_at', '<=', Carbon::parse($to)));
        }

        $sort = $request->input('sort', 'date_asc');
        if ($sort === 'round_asc') {
            $query->orderBy('round_no', 'asc')->orderBy('id', 'asc');
        } elseif ($sort === 'round_desc') {
            $query->orderBy('round_no', 'desc')->orderBy('id', 'desc');
        } elseif ($sort === 'date_desc') {
            $query->orderBy('id', 'desc');
        } else {
            $query->orderBy('id', 'asc');
        }

        $perPage = (int) $request->input('per_page', 15);
        $interviews = $query->paginate($perPage);

        // Bulk load application & job metadata and rounds to prevent N+1 queries
        $applicationIds = $interviews->pluck('application_id')->filter()->unique()->all();
        if (!empty($applicationIds)) {
            if (Schema::hasTable('applications')) {
                $applicationMap = DB::table('applications')
                    ->when(Schema::hasTable('jobs'), fn($q) => $q->leftJoin('jobs', 'jobs.id', '=', 'applications.job_id'))
                    ->when(Schema::hasTable('companies') && Schema::hasTable('jobs'), fn($q) => $q->leftJoin('companies', 'companies.id', '=', 'jobs.company_id'))
                    ->whereIn('applications.id', $applicationIds)
                    ->select([
                        'applications.id as application_id',
                        (Schema::hasColumn('applications', 'status')) ? 'applications.status as application_status' : DB::raw('NULL as application_status'),
                        (Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'title')) ? 'jobs.title as job_title' : DB::raw('NULL as job_title'),
                        (Schema::hasTable('companies') && Schema::hasColumn('companies', 'name')) ? 'companies.name as company_name' : DB::raw('NULL as company_name'),
                    ])
                    ->get()
                    ->keyBy('application_id');

                foreach ($interviews as $interview) {
                    $interview->applicationData = $applicationMap->get($interview->application_id);
                }
            }

            // Bulk load all interview rounds for candidate applications in a single query (0 N+1)
            $roundsGrouped = Interview::query()
                ->whereIn('application_id', $applicationIds)
                ->with('bookings')
                ->orderBy('round_no', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->groupBy('application_id');

            foreach ($interviews as $interview) {
                $interview->applicationRounds = $roundsGrouped->get($interview->application_id, collect());
            }
        }

        return CandidateInterviewResource::collection($interviews)->additional([
            'applied_filters' => [
                'filter' => $filter,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Retrieve interview details for the authenticated candidate.
     * Enforces IDOR protection: candidates can only access their own interviews.
     */
    public function show(Request $request, int $id): CandidateInterviewResource|JsonResponse
    {
        $interview = Interview::query()
            ->with(['bookings', 'evaluations'])
            ->findOrFail($id);

        // Prevent IDOR using policy
        Gate::authorize('viewCandidate', $interview);

        // Load all rounds for the candidate's application ordered chronologically
        $interview->applicationRounds = Interview::query()
            ->where('application_id', $interview->application_id)
            ->with('bookings')
            ->orderBy('round_no', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return new CandidateInterviewResource($interview);
    }

    /**
     * Book an interview slot as an authenticated candidate.
     */
    public function book(\App\Http\Requests\CandidateBookInterviewRequest $request, \App\Services\InterviewSchedulingService $service): JsonResponse
    {
        $interview = $service->bookCandidateSlot(
            $request->validated(),
            $request->user()->id
        );

        return (new CandidateInterviewResource($interview))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Reschedule an interview as an authenticated candidate (enforcing 24-hour policy).
     */
    public function reschedule(Request $request, int $id, \App\Services\InterviewSchedulingService $service): CandidateInterviewResource
    {
        $request->validate([
            'new_availability_id' => ['required', 'integer'],
        ]);

        $interview = Interview::query()->with('bookings')->findOrFail($id);

        Gate::authorize('viewCandidate', $interview);

        $booking = $interview->bookings()
            ->where('booking_status', 'confirmed')
            ->firstOrFail();

        $service->rescheduleBooking($booking, $request->input('new_availability_id'));

        return new CandidateInterviewResource($interview->fresh(['bookings', 'evaluations']));
    }

    /**
     * Cancel an interview as an authenticated candidate (enforcing 24-hour policy).
     */
    public function cancel(Request $request, int $id, \App\Services\InterviewSchedulingService $service): CandidateInterviewResource
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $interview = Interview::query()->with('bookings')->findOrFail($id);

        Gate::authorize('viewCandidate', $interview);

        $cancelledInterview = $service->cancelInterview(
            $interview,
            $request->user()->id,
            $request->input('reason')
        );

        return new CandidateInterviewResource($cancelledInterview->fresh(['bookings', 'evaluations']));
    }
}

