<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreEducationRequest;
use App\Http\Requests\Candidate\UpdateEducationRequest;
use App\Http\Resources\CandidateEducationResource;
use App\Models\CandidateEducation;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidateEducationController extends Controller
{
    /**
     * GET /api/candidate/educations
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $educations = CandidateEducation::where('candidate_id', $profile->id)
            ->orderByDesc('graduation_year')
            ->get();

        return CandidateEducationResource::collection($educations);
    }

    /**
     * POST /api/candidate/educations
     */
    public function store(StoreEducationRequest $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $data = $request->validated();
        $data['candidate_id'] = $profile->id;

        $education = CandidateEducation::create($data);

        return response()->json([
            'message'   => 'Education added successfully.',
            'education' => new CandidateEducationResource($education),
        ], 201);
    }

    /**
     * PUT /api/candidate/educations/{id}
     */
    public function update(UpdateEducationRequest $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $education = CandidateEducation::where('candidate_id', $profile->id)->findOrFail($id);
        $education->update($request->validated());

        return response()->json([
            'message' => 'Education updated successfully.',
            'education' => new CandidateEducationResource($education->fresh()),
        ], 200);
    }

    /**
     * DELETE /api/candidate/educations/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $education = CandidateEducation::where('candidate_id', $profile->id)->findOrFail($id);

        $education->delete();

        return response()->json([
            'message' => 'Education deleted successfully.',
        ], 200);
    }
}

