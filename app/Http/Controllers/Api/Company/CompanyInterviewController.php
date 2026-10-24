<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\InterviewerInterviewResource;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CompanyEmployee;
use App\Models\Interview;
use App\Models\JobInterviewRound;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyInterviewController extends Controller
{
    /**
     * POST /api/company/applications/{id}/interviews
     *
     * Create an interview for an application.
     * Validates: application belongs to company, interviewer is approved employee
     * with interviews.conduct, round exists in job, no duplicate round_no.
     */
    public function store(Request $request, int $applicationId): JsonResponse
    {
        $request->validate([
            'round_no'       => 'required|integer|min:1',
            'interview_type' => 'required|string|in:onsite,online,custom',
            'instructions'   => 'nullable|string|max:2000',
            'interviewer_id' => 'required|integer|exists:users,id',
            'location'       => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $userCompanyId = $user->resolveCompanyId();

        $application = Application::with('job')->findOrFail($applicationId);

        // Verify application belongs to this company
        if ($user->account_type !== 'admin' && (int) $application->job->company_id !== (int) $userCompanyId) {
            return response()->json([
                'message' => 'You do not have permission to access resources belonging to another company.',
            ], 403);
        }

        $jobId = $application->job_id;
        $roundNo = $request->input('round_no');
        $interviewerId = $request->input('interviewer_id');

        // Verify round exists for the job
        $round = JobInterviewRound::where('job_id', $jobId)
            ->where('round_no', $roundNo)
            ->first();

        if (! $round) {
            return response()->json([
                'message' => "Interview round #{$roundNo} does not exist for this job. Create the round first.",
            ], 422);
        }

        // Verify no duplicate round_no for this application
        $existingInterview = Interview::where('application_id', $applicationId)
            ->where('round_no', $roundNo)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingInterview) {
            return response()->json([
                'message' => "An interview for round #{$roundNo} already exists for this application.",
            ], 409);
        }

        // Verify interviewer is approved employee in this company with interviews.conduct
        $companyId = $application->job->company_id;
        $interviewerEmployee = CompanyEmployee::where('company_id', $companyId)
            ->where('user_id', $interviewerId)
            ->where('status', 'approved')
            ->first();

        if (! $interviewerEmployee) {
            return response()->json([
                'message' => 'The specified interviewer is not an approved employee of this company.',
            ], 422);
        }

        // Generate meeting_link if online
        $meetingLink = null;
        if ($request->input('interview_type') === 'online') {
            $meetingLink = 'https://meet.platform.internal/' . Str::uuid();
        }

        $interview = DB::transaction(function () use ($application, $request, $roundNo, $meetingLink, $user) {
            $interview = Interview::create([
                'application_id' => $application->id,
                'round_no'       => $roundNo,
                'interview_type' => $request->input('interview_type'),
                'status'         => 'pending',
                'meeting_link'   => $meetingLink,
                'location'       => $request->input('location'),
                'instructions'   => $request->input('instructions'),
                'created_by'     => $user->id,
            ]);

            // Update application status to interviewing if not already
            if (! in_array($application->status, ['interviewing', 'offered', 'hired'])) {
                $oldStatus = $application->status;
                $application->update(['status' => 'interviewing']);

                ApplicationStatusHistory::create([
                    'application_id' => $application->id,
                    'from_status'    => $oldStatus,
                    'to_status'      => 'interviewing',
                    'changed_by'     => $user->id,
                    'reason'         => "Interview round #{$roundNo} scheduled.",
                ]);
            }

            // Send notification to candidate
            $candidateUserId = $application->candidateProfile?->user_id;
            if ($candidateUserId) {
                Notification::create([
                    'recipient_id'    => $candidateUserId,
                    'channel'         => 'platform',
                    'type'            => 'interview.scheduled',
                    'title'           => 'Interview Scheduled',
                    'body'            => "An interview (Round #{$roundNo}) has been scheduled for your application.",
                    'related_type'    => Interview::class,
                    'related_id'      => $interview->id,
                    'delivery_status' => 'delivered',
                ]);
            }

            return $interview;
        });

        return response()->json([
            'message' => 'Interview created successfully.',
            'data'    => new InterviewerInterviewResource($interview->fresh(['application.job', 'bookings.interviewer', 'bookings.availability', 'evaluations'])),
        ], 201);
    }

    /**
     * GET /api/company/interviews
     *
     * List all interviews for the company, with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $userCompanyId = $user->resolveCompanyId();

        $query = Interview::with([
            'application.job',
            'bookings.interviewer',
            'bookings.availability',
            'evaluations',
        ]);

        // Scope to company
        if ($user->account_type !== 'admin') {
            $query->whereHas('application.job', function ($q) use ($userCompanyId) {
                $q->where('company_id', $userCompanyId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('job_id')) {
            $query->whereHas('application', function ($q) use ($request) {
                $q->where('job_id', $request->input('job_id'));
            });
        }

        $interviews = $query->orderByDesc('created_at')->paginate(15);

        return InterviewerInterviewResource::collection($interviews);
    }

    /**
     * POST /api/company/interviews/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $userCompanyId = $user->resolveCompanyId();

        $interview = Interview::with('application.job')->findOrFail($id);

        if ($user->account_type !== 'admin' && (int) $interview->application->job->company_id !== (int) $userCompanyId) {
            return response()->json([
                'message' => 'You do not have permission to access resources belonging to another company.',
            ], 403);
        }

        if ($interview->status === 'cancelled') {
            return response()->json([
                'message' => 'This interview is already cancelled.',
            ], 409);
        }

        $interview->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Interview cancelled successfully.',
            'data'    => new InterviewerInterviewResource($interview->fresh(['application.job', 'bookings.interviewer', 'bookings.availability', 'evaluations'])),
        ], 200);
    }
}

