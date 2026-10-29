<?php

namespace App\Http\Controllers\Api\Application;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\SubmitApplicationDraftRequest;
use App\Http\Resources\ApplicationDraftResource;
use App\Models\CandidateApplicationDraft;
use App\Models\CandidateCv;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateApplicationDraftController extends Controller
{
    public function show(Request $request, int $jobId): ApplicationDraftResource
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $job = Job::available()->findOrFail($jobId);
        $draft = CandidateApplicationDraft::with('job')
            ->where('candidate_id', $profile->id)
            ->where('job_id', $job->id)
            ->first();

        abort_if(! $draft, 404, 'No application draft exists for this job.');

        return new ApplicationDraftResource($draft);
    }

    public function update(SubmitApplicationDraftRequest $request, int $jobId): ApplicationDraftResource|JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $job = Job::available()->findOrFail($jobId);
        $validated = $request->validated();

        if (array_key_exists('cv_id', $validated) && $validated['cv_id'] !== null
            && ! CandidateCv::where('id', $validated['cv_id'])->where('candidate_id', $profile->id)->exists()) {
            return response()->json([
                'message' => 'The selected CV does not belong to the authenticated candidate.',
                'errors' => ['cv_id' => ['The selected CV is invalid for this candidate.']],
            ], 422);
        }

        $questionIds = collect($validated['answers'] ?? [])->pluck('question_id')->filter()->unique();
        $validQuestionCount = $job->screeningQuestions()->whereIn('id', $questionIds)->count();
        if ($validQuestionCount !== $questionIds->count()) {
            return response()->json([
                'message' => 'One or more screening questions do not belong to this job.',
                'errors' => ['answers' => ['All answers must reference questions for this job.']],
            ], 422);
        }

        $draft = CandidateApplicationDraft::updateOrCreate(
            ['candidate_id' => $profile->id, 'job_id' => $job->id],
            $validated
        );

        return response()->json([
            'data' => new ApplicationDraftResource($draft->load('job')),
        ], 200);
    }
}
