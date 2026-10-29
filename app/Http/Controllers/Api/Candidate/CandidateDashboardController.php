<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Interview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateDashboardController extends Controller
{
    /** GET /api/candidate/dashboard */
    public function index(Request $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $onboarding = $profile->onboarding;
        $activeStatuses = ['submitted', 'screening', 'shortlisted', 'interviewing', 'offered'];

        $applications = Application::query()
            ->with(['job.company'])
            ->where('candidate_id', $profile->id)
            ->whereNull('withdrawn_at')
            ->whereIn('status', $activeStatuses)
            ->latest('applied_at')
            ->get()
            ->map(fn (Application $application) => [
                'id' => $application->id,
                'job_id' => $application->job_id,
                'job_title' => $application->job?->title,
                'company_name' => $application->job?->company?->name,
                'status' => $application->status,
                'applied_at' => $application->applied_at?->toIso8601String(),
            ])->values();

        $nextInterview = Interview::query()
            ->with(['bookings', 'application.job.company'])
            ->forCandidate($request->user()->id)
            ->where('status', '!=', 'cancelled')
            ->whereHas('bookings', fn ($query) => $query
                ->where('start_at', '>=', now())
                ->where('booking_status', '!=', 'cancelled'))
            ->get()
            ->sortBy(fn (Interview $interview) => $interview->bookings
                ->filter(fn ($booking) => $booking->start_at?->isFuture() && $booking->booking_status !== 'cancelled')
                ->sortBy('start_at')
                ->first()?->start_at)
            ->first();

        $nextInterviewData = null;
        if ($nextInterview) {
            $booking = $nextInterview->bookings
                ->filter(fn ($item) => $item->start_at?->isFuture() && $item->booking_status !== 'cancelled')
                ->sortBy('start_at')
                ->first();

            $nextInterviewData = [
                'id' => $nextInterview->id,
                'application_id' => $nextInterview->application_id,
                'job_title' => $nextInterview->application?->job?->title,
                'company_name' => $nextInterview->application?->job?->company?->name,
                'round_no' => $nextInterview->round_no,
                'interview_type' => $nextInterview->interview_type,
                'status' => $nextInterview->status,
                'start_at' => $booking?->start_at?->toIso8601String(),
                'end_at' => $booking?->end_at?->toIso8601String(),
                'location' => $nextInterview->location,
                'meeting_link' => $nextInterview->meeting_link,
            ];
        }

        $completionPercent = (int) ($onboarding?->completion_percent ?? 0);
        $nextActions = [];
        if ($completionPercent < 100) {
            $nextActions[] = ['key' => 'complete_onboarding', 'label' => 'Complete your profile onboarding.'];
        }
        if (! $profile->candidateCvs()->where('is_active', true)->exists()) {
            $nextActions[] = ['key' => 'upload_cv', 'label' => 'Upload an active CV.'];
        }
        if ($applications->isEmpty()) {
            $nextActions[] = ['key' => 'browse_jobs', 'label' => 'Browse jobs and apply to a position.'];
        }

        return response()->json([
            'data' => [
                'profile_completion_percent' => $completionPercent,
                'next_interview' => $nextInterviewData,
                'active_applications' => $applications,
                'next_actions' => $nextActions,
            ],
        ], 200);
    }
}
