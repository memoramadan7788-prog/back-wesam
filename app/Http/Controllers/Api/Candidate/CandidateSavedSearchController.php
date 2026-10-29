<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreSavedSearchRequest;
use App\Http\Requests\Candidate\UpdateSavedSearchRequest;
use App\Models\CandidateProfile;
use App\Models\CandidateSavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateSavedSearchController extends Controller
{
    /** GET /api/candidate/saved-searches */
    public function index(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        return response()->json([
            'data' => $profile->savedSearches()->latest()->get()->map(fn (CandidateSavedSearch $search) => $this->data($search))->values(),
        ], 200);
    }

    /** POST /api/candidate/saved-searches */
    public function store(StoreSavedSearchRequest $request): JsonResponse
    {
        $search = $this->profile($request)->savedSearches()->create($request->validated());

        return response()->json(['data' => $this->data($search)], 201);
    }

    /** PUT /api/candidate/saved-searches/{id} */
    public function update(UpdateSavedSearchRequest $request, int $id): JsonResponse
    {
        $search = $this->profile($request)->savedSearches()->findOrFail($id);
        $search->update($request->validated());

        return response()->json(['data' => $this->data($search->fresh())], 200);
    }

    /** DELETE /api/candidate/saved-searches/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $search = $this->profile($request)->savedSearches()->findOrFail($id);
        $search->delete();

        return response()->json(['message' => 'Saved search deleted successfully.'], 200);
    }

    private function profile(Request $request): CandidateProfile
    {
        return CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
    }

    private function data(CandidateSavedSearch $search): array
    {
        return [
            'id' => $search->id,
            'name' => $search->name,
            'filters' => $search->filters,
            'weekly_alerts' => (bool) $search->weekly_alerts,
            'is_paused' => (bool) $search->is_paused,
            'last_alerted_at' => $search->last_alerted_at?->toIso8601String(),
            'created_at' => $search->created_at?->toIso8601String(),
            'updated_at' => $search->updated_at?->toIso8601String(),
        ];
    }
}
