<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UploadCvRequest;
use App\Http\Resources\CandidateCvResource;
use App\Models\CandidateCv;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidateCvController extends Controller
{
    /**
     * GET /api/candidate/cvs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $cvs = CandidateCv::where('candidate_id', $profile->id)
            ->orderByDesc('version')
            ->get();

        return CandidateCvResource::collection($cvs);
    }

    /**
     * POST /api/candidate/cvs
     */
    public function store(UploadCvRequest $request): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $file = $request->file('cv');

        // withTrashed(): soft-deleted CVs still occupy the (candidate_id, version) unique key.
        $latestVersion = (int) (CandidateCv::withTrashed()->where('candidate_id', $profile->id)->max('version') ?? 0);
        $nextVersion = $latestVersion + 1;

        $storedPath = $file->store("cvs/{$profile->id}", 'local');

        $hasActive = CandidateCv::where('candidate_id', $profile->id)->where('is_active', true)->exists();

        $cv = CandidateCv::create([
            'candidate_id'      => $profile->id,
            'version'           => $nextVersion,
            'storage_path'      => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type'         => $file->getClientMimeType() ?: 'application/pdf',
            'size_bytes'        => $file->getSize(),
            'is_active'         => ! $hasActive,
            'uploaded_at'       => now(),
        ]);

        return response()->json([
            'message' => 'CV uploaded successfully.',
            'cv'      => new CandidateCvResource($cv),
        ], 201);
    }

    /**
     * PATCH /api/candidate/cvs/{id}/set-active
     */
    public function setActive(Request $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $cv = CandidateCv::where('candidate_id', $profile->id)->findOrFail($id);

        CandidateCv::where('candidate_id', $profile->id)->update(['is_active' => false]);
        $cv->update(['is_active' => true]);

        return response()->json([
            'message' => 'Active CV updated successfully.',
            'cv'      => new CandidateCvResource($cv),
        ], 200);
    }

    /**
     * DELETE /api/candidate/cvs/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $cv = CandidateCv::where('candidate_id', $profile->id)->findOrFail($id);

        $cv->delete();

        return response()->json([
            'message' => 'CV deleted successfully.',
        ], 200);
    }
}