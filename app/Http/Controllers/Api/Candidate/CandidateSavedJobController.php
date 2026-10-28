<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidateSavedJobController extends Controller
{
    /**
     * GET /api/candidate/saved-jobs
     * List saved jobs for the authenticated candidate with pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $candidate = CandidateProfile::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $savedJobs = $candidate->savedJobs()
            ->select(Job::PUBLIC_COLUMNS)
            ->with([
                'company:id,name,industry,website,logo_path',
                'category:id,name',
                'skills' => fn ($q) => $q->select('skills.id', 'skills.name', 'skills.type'),
            ])
            ->orderByPivot('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return JobResource::collection($savedJobs);
    }

    /**
     * POST /api/candidate/saved-jobs/{jobId}
     * Bookmark / Save a job.
     */
    public function store(Request $request, int $jobId): JsonResponse
    {
        $candidate = CandidateProfile::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $job = Job::query()->available()->find($jobId);

        if (!$job) {
            return response()->json([
                'message' => 'Job not found or is no longer available.',
            ], 404);
        }

        if ($candidate->savedJobs()->where('job_id', $jobId)->exists()) {
            return response()->json([
                'message' => 'Job is already saved.',
                'job_id'  => $jobId,
            ], 200);
        }

        $candidate->savedJobs()->attach($jobId);

        return response()->json([
            'message' => 'Job saved successfully.',
            'job_id'  => $jobId,
        ], 201);
    }

    /**
     * DELETE /api/candidate/saved-jobs/{jobId}
     * Remove a saved job.
     */
    public function destroy(Request $request, int $jobId): JsonResponse
    {
        $candidate = CandidateProfile::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $detached = $candidate->savedJobs()->detach($jobId);

        if ($detached === 0) {
            return response()->json([
                'message' => 'Job not found in your saved list.',
            ], 404);
        }

        return response()->json([
            'message' => 'Job removed from saved list successfully.',
            'job_id'  => $jobId,
        ], 200);
    }
}
