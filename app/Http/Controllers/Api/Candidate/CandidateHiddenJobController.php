<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Models\CandidateHiddenJob;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateHiddenJobController extends Controller
{
    /** POST /api/candidate/recommended-jobs/{jobId}/hide */
    public function store(Request $request, int $jobId): JsonResponse
    {
        $job = Job::available()->findOrFail($jobId);
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $hidden = CandidateHiddenJob::firstOrCreate([
            'candidate_id' => $profile->id,
            'job_id' => $job->id,
        ], [
            'reason' => $request->input('reason'),
        ]);

        return response()->json([
            'message' => 'Job hidden from recommendations.',
            'job_id' => $hidden->job_id,
        ], $hidden->wasRecentlyCreated ? 201 : 200);
    }
}
