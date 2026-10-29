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
        $user = $request->user();
        $userCompanyId = $user->resolveCompanyId();

        $job = Job::findOrFail($jobId);

        if ($user->account_type !== 'admin' && (int) $job->company_id !== (int) $userCompanyId) {
            abort(403, 'You do not have permission to access resources belonging to another company.');
        }

        $status = $request->query('status');
        $source = $request->query('source');
        $ownerId = $request->query('owner_id');
        $search = $request->query('search');

        $applications = Application::with(['candidateProfile.user', 'job.company'])
            ->where('job_id', $job->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($source, fn ($q) => $q->where('source', $source))
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->when($search, fn ($q) => $q->where(function ($w) use ($search) {
                $w->whereHas('candidateProfile.user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('candidateProfile', fn ($p) => $p->where('headline', 'like', "%{$search}%"));
            }))
            ->orderByDesc('applied_at')
            ->paginate(20);

        $grouped = Application::where('job_id', $job->id)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        return ApplicationResource::collection($applications)->additional(['meta' => ['stage_counts' => $grouped]]);
    }

    /**
     * GET /api/company/applications/{id}
     */
    public function show(Request $request, int $id): ApplicationDetailResource
    {
        $user = $request->user();
        $userCompanyId = $user->resolveCompanyId();

        $application = Application::with([
            'job.company',
            'candidateProfile.user',
            'candidateCv',
            'applicationStatusHistories.changer',
        ])->findOrFail($id);

        if ($user->account_type !== 'admin' && (int) $application->job->company_id !== (int) $userCompanyId) {
            abort(403, 'You do not have permission to access resources belonging to another company.');
        }

        return new ApplicationDetailResource($application);
    }

    /**
     * PATCH /api/company/applications/{id}/status
     *
     * Enforces a state machine for application stage transitions.
     */
    public function updateStatus(UpdateApplicationStageRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $userCompanyId = $user->resolveCompanyId();

        $application = Application::with('job')->findOrFail($id);

        if ($user->account_type !== 'admin' && (int) $application->job->company_id !== (int) $userCompanyId) {
            return response()->json([
                'message' => 'You do not have permission to access resources belonging to another company.',
            ], 403);
        }
        $validated = $request->validated();

        $oldStatus = $application->status;
        $stageMap = ['applied' => 'submitted', 'screening' => 'screening', 'interview' => 'interviewing', 'offer' => 'offered', 'hired' => 'hired'];
        $newStatus = $stageMap[$validated['status']] ?? $validated['status'];

        // Stage transition state machine
        $allowedTransitions = [
            'submitted'    => ['screening', 'shortlisted', 'rejected', 'withdrawn'],
            'screening'    => ['shortlisted', 'rejected', 'withdrawn'],
            'shortlisted'  => ['interviewing', 'rejected', 'withdrawn'],
            'interviewing' => ['offered', 'rejected', 'withdrawn'],
            'offered'      => ['hired', 'rejected', 'withdrawn'],
            'rejected'     => [],
            'hired'        => [],
            'withdrawn'    => [],
        ];

        $allowed = $allowedTransitions[$oldStatus] ?? [];
        if (! in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "Cannot transition from '{$oldStatus}' to '{$newStatus}'.",
                'allowed_transitions' => $allowed,
            ], 409);
        }

        DB::transaction(function () use ($application, $validated, $request, $oldStatus, $newStatus) {
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

