<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\SubmitApplicationRequest;
use App\Http\Resources\ApplicationDetailResource;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateCv;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CandidateApplicationController extends Controller
{
    /**
     * GET /api/candidate/applications
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $applications = Application::with(['job.company'])
            ->where('candidate_id', $profile->id)
            ->orderByDesc('applied_at')
            ->paginate(15);

        return ApplicationResource::collection($applications);
    }

    /**
     * POST /api/jobs/{jobId}/apply
     */
    public function apply(SubmitApplicationRequest $request, int $jobId): JsonResponse
    {
        $job = Job::available()->findOrFail($jobId);
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $validated = $request->validated();

        $activeApplication = Application::where('job_id', $job->id)
            ->where('candidate_id', $profile->id)
            ->whereNull('withdrawn_at')
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->first();

        if ($activeApplication) {
            return response()->json([
                'message' => 'You already have an active application for this position.',
            ], 409);
        }

        $cvId = $validated['cv_id'] ?? CandidateCv::where('candidate_id', $profile->id)->where('is_active', true)->value('id');

        $latestAttempt = (int) (Application::where('job_id', $job->id)->where('candidate_id', $profile->id)->max('attempt_no') ?? 0);

        $application = DB::transaction(function () use ($job, $profile, $cvId, $validated, $latestAttempt, $request) {
            $app = Application::create([
                'job_id'       => $job->id,
                'candidate_id' => $profile->id,
                'cv_id'        => $cvId,
                'cover_note'   => $validated['cover_note'] ?? null,
                'status'       => 'submitted',
                'attempt_no'   => $latestAttempt + 1,
                'applied_at'   => now(),
                'consent_at'   => ($validated['consent'] ?? false) ? now() : null,
            ]);

            ApplicationStatusHistory::create([
                'application_id' => $app->id,
                'from_status'    => null,
                'to_status'      => 'submitted',
                'changed_by'     => $request->user()->id,
                'reason'         => 'Application submitted by candidate.',
            ]);

            return $app;
        });

        return response()->json([
            'message'     => 'Application submitted successfully.',
            'application' => new ApplicationDetailResource($application->load(['job.company', 'candidateProfile.user', 'candidateCv', 'applicationStatusHistories'])),
        ], 201);
    }

    /**
     * GET /api/candidate/applications/{id}
     */
    public function show(Request $request, int $id): ApplicationDetailResource
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $application = Application::with(['job.company', 'candidateProfile.user', 'candidateCv', 'applicationStatusHistories.changer'])
            ->where('candidate_id', $profile->id)
            ->findOrFail($id);

        return new ApplicationDetailResource($application);
    }

    /**
     * DELETE /api/candidate/applications/{id}/withdraw
     */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $application = Application::where('candidate_id', $profile->id)
            ->whereNull('withdrawn_at')
            ->findOrFail($id);

        DB::transaction(function () use ($application, $request) {
            $oldStatus = $application->status;
            $application->update([
                'status'       => 'withdrawn',
                'withdrawn_at' => now(),
            ]);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status'    => $oldStatus,
                'to_status'      => 'withdrawn',
                'changed_by'     => $request->user()->id,
                'reason'         => 'Application withdrawn by candidate.',
            ]);
        });

        return response()->json([
            'message' => 'Application withdrawn successfully.',
        ], 200);
    }
}

