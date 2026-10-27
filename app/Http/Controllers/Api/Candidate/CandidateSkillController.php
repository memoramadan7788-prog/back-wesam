<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Models\CandidateProfile;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateSkillController extends Controller
{
    /**
     * GET /api/candidate/skills
     * Retrieve all skills assigned to the authenticated candidate.
     */
    public function index(Request $request): JsonResponse
    {
        $candidate = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $skills = $candidate->skills()->get()->map(fn ($s) => [
            'id'    => $s->id,
            'name'  => $s->name,
            'type'  => $s->type,
            'level' => $s->pivot->level,
        ]);

        return response()->json([
            'data' => $skills,
        ]);
    }

    /**
     * POST /api/candidate/skills
     * Sync candidate skills (supports array of IDs or array of objects with skill_id and level).
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skills'             => ['required', 'array'],
            'skills.*'           => ['nullable'],
            'skills.*.skill_id'  => ['nullable', 'integer', 'exists:skills,id'],
            'skills.*.id'        => ['nullable', 'integer', 'exists:skills,id'],
            'skills.*.level'     => ['nullable', 'string', 'max:50'],
        ]);

        $candidate = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $syncData = [];
        foreach ($validated['skills'] as $item) {
            if (is_numeric($item)) {
                $syncData[$item] = ['level' => null];
            } elseif (is_array($item)) {
                $skillId = $item['skill_id'] ?? $item['id'] ?? null;
                if ($skillId) {
                    $syncData[$skillId] = ['level' => $item['level'] ?? null];
                }
            }
        }

        $candidate->skills()->sync($syncData);

        return response()->json([
            'message' => 'Candidate skills updated successfully.',
            'data'    => $candidate->skills()->get()->map(fn ($s) => [
                'id'    => $s->id,
                'name'  => $s->name,
                'type'  => $s->type,
                'level' => $s->pivot->level,
            ]),
        ]);
    }
}
