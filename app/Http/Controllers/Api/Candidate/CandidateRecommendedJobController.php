<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidateRecommendedJobController extends Controller
{
    /**
     * GET /api/candidate/recommended-jobs
     * List recommended published jobs matching candidate's profile, skills, experience, and location.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $candidate = CandidateProfile::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $candidateSkillIds = $candidate->skills()->pluck('skills.id')->toArray();
        $appliedJobIds = $candidate->applications()->pluck('job_id')->toArray();

        $query = Job::query()
            ->available()
            ->select(Job::PUBLIC_COLUMNS)
            ->with([
                'company:id,name,industry,website,logo_path',
                'category:id,name',
                'skills' => fn ($q) => $q->select('skills.id', 'skills.name', 'skills.type'),
            ]);

        // Exclude already applied jobs
        if (!empty($appliedJobIds)) {
            $query->whereNotIn('jobs.id', $appliedJobIds);
        }

        // Skill matching
        if (!empty($candidateSkillIds)) {
            $query->withCount([
                'skills as matching_skills_count' => function ($q) use ($candidateSkillIds) {
                    $q->whereIn('skills.id', $candidateSkillIds);
                },
            ]);

            // Experience matching: prioritize jobs where candidate has enough experience
            if ($candidate->years_experience !== null) {
                $query->orderByRaw('CASE WHEN jobs.experience_years <= ? THEN 1 ELSE 0 END DESC', [$candidate->years_experience]);
            }

            // Location matching: prioritize jobs matching candidate location or remote
            if (!empty($candidate->location)) {
                $query->orderByRaw('CASE WHEN jobs.location LIKE ? OR jobs.work_mode = "remote" THEN 1 ELSE 0 END DESC', ['%' . $candidate->location . '%']);
            }

            // Order by highest matching skills count first, then newest published
            $query->orderByDesc('matching_skills_count')
                  ->orderByDesc('jobs.published_at')
                  ->orderByDesc('jobs.id');
        } else {
            // Fallback ranking when candidate has no skills yet:
            if ($candidate->years_experience !== null) {
                $query->orderByRaw('CASE WHEN jobs.experience_years <= ? THEN 1 ELSE 0 END DESC', [$candidate->years_experience]);
            }

            if (!empty($candidate->location)) {
                $query->orderByRaw('CASE WHEN jobs.location LIKE ? OR jobs.work_mode = "remote" THEN 1 ELSE 0 END DESC', ['%' . $candidate->location . '%']);
            }

            $query->orderByDesc('jobs.published_at')
                  ->orderByDesc('jobs.id');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $jobs = $query->paginate($perPage)->withQueryString();

        return JobResource::collection($jobs);
    }
}
