<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Resources\CandidateProfileResource;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateProfileController extends Controller
{
    /**
     * GET /api/candidate/profile
     */
    public function show(Request $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        return (new CandidateProfileResource(
            $profile->load(['user', 'candidateExperiences', 'candidateEducations', 'candidateCvs'])
        ))->response()->setStatusCode(200);
    }

    /**
     * PUT /api/candidate/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        $profile->update($request->validated());

        return (new CandidateProfileResource(
            $profile->load(['user', 'candidateExperiences', 'candidateEducations', 'candidateCvs'])
        ))->response()->setStatusCode(200);
    }
}

