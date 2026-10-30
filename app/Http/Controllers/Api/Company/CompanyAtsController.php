<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\AtsDecisionRequest;
use App\Http\Resources\CvMatchResultResource;
use App\Models\AiReviewDecision;
use App\Models\Application;
use App\Models\CvMatchResult;
use App\Services\Ats\AtsEvaluationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAtsController extends Controller
{
   
    public function store(Request $request, int $id, AtsEvaluationDispatcher $dispatcher): JsonResponse
    {
        $application = $this->scopedApplication($request, $id);

        if ($pending = $dispatcher->pendingFor($application)) {
            return response()->json([
                'message' => 'An evaluation for this application is already in progress.',
                'data' => new CvMatchResultResource($pending),
            ], 409);
        }

        $result = $dispatcher->dispatchFor($application, $request->user()->id);

        if (! $result) {
            return response()->json([
                'message' => 'This application has no CV to evaluate.',
            ], 422);
        }

        return response()->json([
            'message' => 'Evaluation queued. Poll the GET endpoint for the result.',
            'data' => new CvMatchResultResource($result),
        ], 202);
    }

   
    public function show(Request $request, int $id): JsonResponse
    {
        $application = $this->scopedApplication($request, $id);

        $result = CvMatchResult::with('latestDecision')
            ->where('application_id', $application->id)
            ->latest('id')
            ->first();

       
        if (! $result) {
            return response()->json([
                'message' => 'No ATS evaluation exists for this application yet.',
                'data' => null,
            ]);
        }

        return response()->json(['data' => new CvMatchResultResource($result)]);
    }

   
    public function decide(AtsDecisionRequest $request, int $id): JsonResponse
    {
        $application = $this->scopedApplication($request, $id);

        $result = CvMatchResult::where('application_id', $application->id)
            ->where('run_status', CvMatchResult::OK)
            ->latest('id')
            ->first();

        if (! $result) {
            return response()->json(['message' => 'There is no completed ATS evaluation to review.'], 422);
        }

        $decision = AiReviewDecision::create([
            'match_result_id' => $result->id,
            'reviewer_id' => $request->user()->id,
            'decision' => $request->validated('decision'),
            'reason' => $request->validated('reason'),
            'decided_at' => now(),
        ]);

        return response()->json([
            'message' => 'Review decision saved.',
            'data' => [
                'id' => $decision->id,
                'match_result_id' => $result->id,
                'decision' => $decision->decision,
                'reason' => $decision->reason,
            ],
        ], 201);
    }

   
    private function scopedApplication(Request $request, int $id): Application
    {
        $user = $request->user();
        $application = Application::with('job')->findOrFail($id);

        if ($user->account_type !== 'admin'
            && (int) $application->job->company_id !== (int) $user->resolveCompanyId()) {
            abort(403, 'You do not have permission to access resources belonging to another company.');
        }

        return $application;
    }
}