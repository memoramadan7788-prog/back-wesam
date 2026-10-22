<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreExperienceRequest;
use App\Http\Resources\CandidateExperienceResource;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidateExperienceController extends Controller
{
    /**
     * GET /api/candidate/experiences
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $experiences = CandidateExperience::where('candidate_id', $profile->id)
            ->orderByDesc('start_date')
            ->get();

        return CandidateExperienceResource::collection($experiences);
    }

    /**
     * POST /api/candidate/experiences
     */
    public function store(StoreExperienceRequest $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $data = $request->validated();
        $data['candidate_id'] = $profile->id;

        $experience = CandidateExperience::create($data);

        return response()->json([
            'message'    => 'Experience added successfully.',
            'experience' => new CandidateExperienceResource($experience),
        ], 201);
    }

    /**
     * DELETE /api/candidate/experiences/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $experience = CandidateExperience::where('candidate_id', $profile->id)->findOrFail($id);

        $experience->delete();

        return response()->json([
            'message' => 'Experience deleted successfully.',
        ], 200);
    }
}

