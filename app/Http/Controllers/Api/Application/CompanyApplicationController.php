<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\UpdateApplicationStageRequest;
use App\Http\Resources\ApplicationDetailResource;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CompanyApplicationController extends Controller
{
    /**
     * GET /api/company/jobs/{jobId}/applications
     */
    public function index(Request $request, int $jobId): AnonymousResourceCollection
    {
        $job = Job::findOrFail($jobId);

        $status = $request->query('status');

        $applications = Application::with(['candidateProfile.user', 'job.company'])
            ->where('job_id', $job->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('applied_at')
            ->paginate(20);

        return ApplicationResource::collection($applications);
    }

    /**
     * GET /api/company/applications/{id}
     */
    public function show(Request $request, int $id): ApplicationDetailResource
    {
        $application = Application::with([
            'job.company',
            'candidateProfile.user',
            'candidateCv',
            'applicationStatusHistories.changer',
        ])->findOrFail($id);

        return new ApplicationDetailResource($application);
    }

    /**
     * PATCH /api/company/applications/{id}/status
     */
    public function updateStatus(UpdateApplicationStageRequest $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($application, $validated, $request) {
            $oldStatus = $application->status;
            $newStatus = $validated['status'];

            $application->update([
                'status' => $newStatus,
            ]);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status'    => $oldStatus,
                'to_status'      => $newStatus,
                'changed_by'     => $request->user()->id,
                'reason'         => $validated['reason'] ?? $validated['comment'] ?? null,
            ]);
        });

        return response()->json([
            'message'     => 'Application status updated successfully.',
            'application' => new ApplicationDetailResource($application->fresh(['job.company', 'candidateProfile.user', 'candidateCv', 'applicationStatusHistories.changer'])),
        ], 200);
    }
}

