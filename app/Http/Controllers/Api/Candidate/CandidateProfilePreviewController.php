<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\CandidateProfilePreviewResource;
use App\Models\CandidateProfile;
use Illuminate\Http\Request;

class CandidateProfilePreviewController extends Controller
{
    /** GET /api/candidate/profile/preview */
    public function show(Request $request): CandidateProfilePreviewResource
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        return new CandidateProfilePreviewResource($profile->load([
            'user',
            'skills',
            'candidateExperiences',
            'candidateEducations',
            'candidateCvs',
        ]));
    }
}
