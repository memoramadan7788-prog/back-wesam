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

        if ($cvId !== null && ! CandidateCv::where('id', $cvId)->where('candidate_id', $profile->id)->exists()) {
            return response()->json([
                'message' => 'The selected CV does not belong to the authenticated candidate.',
                'errors' => ['cv_id' => ['The selected CV is invalid for this candidate.']],
            ], 422);
        }

        $questionError = $this->validateScreeningAnswers($validated['answers'] ?? [], $job);
        if ($questionError) {
            return $questionError;
        }

        $latestAttempt = (int) (Application::where('job_id', $job->id)->where('candidate_id', $profile->id)->max('attempt_no') ?? 0);

        $application = DB::transaction(function () use ($job, $profile, $cvId, $validated, $latestAttempt, $request) {
            $app = Application::create([
                'job_id'       => $job->id,
                'candidate_id' => $profile->id,
                'cv_id'        => $cvId,
                'cover_note'   => $validated['cover_note'] ?? null,
                'answers'      => $validated['answers'] ?? null,
                'expected_salary' => $validated['expected_salary'] ?? null,
                'portfolio_url' => $validated['portfolio_url'] ?? null,
                'keep_in_talent_pool' => $validated['keep_in_talent_pool'] ?? false,
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
            'application' => new ApplicationDetailResource($application->load($this->detailRelations())),
        ], 201);
    }

    /**
     * GET /api/candidate/applications/{id}
     */
    public function show(Request $request, int $id): ApplicationDetailResource
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $application = Application::with($this->detailRelations())
            ->where('candidate_id', $profile->id)
            ->findOrFail($id);

        return new ApplicationDetailResource($application);
    }

    /** GET /api/candidate/applications/{id}/activity */
    public function activity(Request $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $application = Application::with([
            'applicationStatusHistories.changer',
            'interviews.bookings',
            'offers.sender',
        ])->where('candidate_id', $profile->id)->findOrFail($id);

        $events = collect();
        foreach ($application->applicationStatusHistories as $history) {
            $events->push([
                'type' => 'status_change',
                'title' => 'Application status updated',
                'status' => $history->to_status,
                'description' => $history->reason,
                'changed_by' => $history->changer?->name,
                'occurred_at' => ($history->changed_at ?? $history->created_at)?->toIso8601String(),
            ]);
        }

        foreach ($application->interviews as $interview) {
            $booking = $interview->bookings
                ->where('booking_status', '!=', 'cancelled')
                ->sortBy('start_at')
                ->first();
            $events->push([
                'type' => 'interview',
                'title' => 'Interview scheduled',
                'status' => $interview->status,
                'description' => $interview->instructions,
                'interview_id' => $interview->id,
                'round_no' => $interview->round_no,
                'start_at' => $booking?->start_at?->toIso8601String(),
                'occurred_at' => $interview->created_at?->toIso8601String(),
            ]);
        }

        foreach ($application->offers as $offer) {
            $events->push([
                'type' => 'offer',
                'title' => 'Offer status updated',
                'status' => $offer->status,
                'offer_id' => $offer->id,
                'changed_by' => $offer->sender?->name,
                'occurred_at' => ($offer->responded_at ?? $offer->sent_at ?? $offer->created_at)?->toIso8601String(),
            ]);
        }

        return response()->json([
            'data' => $events->sortBy('occurred_at')->values()->all(),
        ]);
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

    private function detailRelations(): array
    {
        return [
            'job.company.owner',
            'job.company.companyEmployees.user',
            'job.company.companyEmployees.role',
            'candidateProfile.user',
            'candidateCv',
            'applicationStatusHistories.changer',
            'interviews.bookings.interviewer',
            'offers.sender',
        ];
    }

    private function validateScreeningAnswers(array $answers, Job $job): ?JsonResponse
    {
        $questions = $job->screeningQuestions()->get();
        $questionIds = collect($answers)->pluck('question_id')->filter()->unique();
        $validIds = $questions->pluck('id');

        if ($questionIds->diff($validIds)->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more screening questions do not belong to this job.',
                'errors' => ['answers' => ['All answers must reference questions for this job.']],
            ], 422);
        }

        $answersByQuestion = collect($answers)->keyBy('question_id');
        $missingRequired = $questions
            ->where('is_required', true)
            ->filter(function ($question) use ($answersByQuestion) {
                $answer = $answersByQuestion->get($question->id);
                if (! $answer) {
                    return true;
                }

                return ! collect([
                    $answer['answer'] ?? null,
                    $answer['value'] ?? null,
                    $answer['selected_option_ids'] ?? null,
                ])->contains(function ($value) {
                    return is_array($value) ? ! empty($value) : $value !== null && $value !== '';
                });
            })
            ->pluck('id')
            ->values();

        if ($missingRequired->isNotEmpty()) {
            return response()->json([
                'message' => 'Please answer all required screening questions.',
                'errors' => ['answers' => ['Missing required question ids: ' . $missingRequired->implode(', ') . '.']],
            ], 422);
        }

        return null;
    }
}

