<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreJobInterviewRoundRequest;
use App\Http\Requests\Company\UpdateJobInterviewRoundRequest;
use App\Http\Resources\JobInterviewRoundResource;
use App\Models\Job;
use App\Models\JobInterviewRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobInterviewRoundController extends Controller
{
    /**
     * Helper to verify company ownership.
     */
    protected function authorizeJobOwnership(Request $request, int $jobId): Job
    {
        $job = Job::findOrFail($jobId);
        $user = $request->user();
        $userCompanyId = $user->resolveCompanyId();

        if ($user->account_type !== 'admin' && (int) $job->company_id !== (int) $userCompanyId) {
            abort(403, 'You do not have permission to manage interview rounds for another company.');
        }

        return $job;
    }

    /**
     * GET /api/company/jobs/{job}/interview-rounds
     */
    public function index(Request $request, int $jobId): AnonymousResourceCollection
    {
        $job = $this->authorizeJobOwnership($request, $jobId);

        $rounds = JobInterviewRound::where('job_id', $job->id)
            ->orderBy('round_no')
            ->get();

        return JobInterviewRoundResource::collection($rounds);
    }

    /**
     * POST /api/company/jobs/{job}/interview-rounds
     */
    public function store(StoreJobInterviewRoundRequest $request, int $jobId): JsonResponse
    {
        $job = $this->authorizeJobOwnership($request, $jobId);
        $validated = $request->validated();

        $round = JobInterviewRound::create([
            'job_id'         => $job->id,
            'round_no'       => $validated['round_no'],
            'interview_type' => $validated['interview_type'],
            'duration_min'   => $validated['duration_min'],
        ]);

        return response()->json([
            'message' => 'Interview round created successfully.',
            'round'   => new JobInterviewRoundResource($round),
            'data'    => new JobInterviewRoundResource($round),
        ], 201);
    }

    /**
     * GET /api/company/jobs/{job}/interview-rounds/{round}
     */
    public function show(Request $request, int $jobId, int $roundId): JsonResponse
    {
        $job = $this->authorizeJobOwnership($request, $jobId);

        $round = JobInterviewRound::where('job_id', $job->id)
            ->findOrFail($roundId);

        return response()->json([
            'round' => new JobInterviewRoundResource($round),
            'data'  => new JobInterviewRoundResource($round),
        ]);
    }

    /**
     * PUT/PATCH /api/company/jobs/{job}/interview-rounds/{round}
     */
    public function update(UpdateJobInterviewRoundRequest $request, int $jobId, int $roundId): JsonResponse
    {
        $job = $this->authorizeJobOwnership($request, $jobId);

        $round = JobInterviewRound::where('job_id', $job->id)
            ->findOrFail($roundId);

        $round->update($request->validated());

        return response()->json([
            'message' => 'Interview round updated successfully.',
            'round'   => new JobInterviewRoundResource($round),
            'data'    => new JobInterviewRoundResource($round),
        ]);
    }

    /**
     * DELETE /api/company/jobs/{job}/interview-rounds/{round}
     */
    public function destroy(Request $request, int $jobId, int $roundId): JsonResponse
    {
        $job = $this->authorizeJobOwnership($request, $jobId);

        $round = JobInterviewRound::where('job_id', $job->id)
            ->findOrFail($roundId);

        $round->delete();

        return response()->json([
            'message' => 'Interview round deleted successfully.',
        ], 200);
    }
}

