<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitEvaluationRequest;
use App\Http\Resources\InterviewEvaluationResource;
use App\Models\Interview;
use App\Models\InterviewEvaluation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InterviewEvaluationController extends Controller
{
    /**
     * List evaluations for a specific interview.
     * Accessible by interviewers and HR; internal notes/scores strictly forbidden to candidates.
     */
    public function index(Request $request, int $id): AnonymousResourceCollection
    {
        $interview = Interview::query()->findOrFail($id);

        Gate::authorize('viewInterviewer', $interview);

        $evaluations = $interview->evaluations()->with('evaluator')->get();

        return InterviewEvaluationResource::collection($evaluations);
    }

    /**
     * Submit an evaluation for an interview (ERD Page 4).
     * Note: Evaluator ID is securely derived from the authenticated user.
     * feedback_shared_at cannot be set by evaluation submission flow.
     */
    public function store(SubmitEvaluationRequest $request, int $id): JsonResponse
    {
        $interview = Interview::query()->findOrFail($id);

        Gate::authorize('evaluate', $interview);

        $evaluatorId = $request->user()->id;

        $existing = InterviewEvaluation::query()
            ->where('interview_id', $interview->id)
            ->where('evaluator_id', $evaluatorId)
            ->first();

        $evaluation = InterviewEvaluation::updateOrCreate(
            [
                'interview_id' => $interview->id,
                'evaluator_id' => $evaluatorId,
            ],
            [
                'numeric_score' => $request->validated('numeric_score'),
                'internal_notes' => $request->validated('internal_notes'),
                'candidate_feedback' => $request->validated('candidate_feedback'),
                // Preserve existing feedback_shared_at if already set by trusted workflow; otherwise null
                'feedback_shared_at' => $existing?->feedback_shared_at,
            ]
        );

        return (new InterviewEvaluationResource($evaluation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Publish an evaluation to share candidate feedback and numeric score.
     * Route: POST /api/interviews/{id}/evaluations/{evaluationId}/publish
     */
    public function publish(Request $request, int $id, int $evaluationId): JsonResponse
    {
        $interview = Interview::query()->findOrFail($id);
        $evaluation = InterviewEvaluation::query()->findOrFail($evaluationId);

        // Verify that the evaluation belongs to the interview identified in the route
        if ((int) $evaluation->interview_id !== (int) $interview->id) {
            return response()->json([
                'message' => 'The specified evaluation does not belong to this interview.',
            ], 404);
        }

        Gate::authorize('publishEvaluation', [$interview, $evaluation]);

        return DB::transaction(function () use ($interview, $evaluation) {
            // Idempotent: preserve original timestamp if already published
            if ($evaluation->feedback_shared_at === null) {
                $evaluation->update([
                    'feedback_shared_at' => Carbon::now(),
                ]);
            }

            return response()->json([
                'message' => 'Evaluation feedback published successfully.',
                'data' => [
                    'id' => $evaluation->id,
                    'interview_id' => $evaluation->interview_id,
                    'feedback_shared_at' => $evaluation->feedback_shared_at?->toIso8601String(),
                    'is_published' => true,
                ],
            ], 200);
        });
    }
}
