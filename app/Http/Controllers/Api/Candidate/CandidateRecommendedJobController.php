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
        $hiddenJobIds = $candidate->hiddenJobs()->pluck('job_id')->toArray();
        $hideIrrelevant = $request->has('hide_irrelevant')
            ? $request->boolean('hide_irrelevant')
            : true;
        $includeApplied = $request->boolean('include_applied', false);

        $query = Job::query()
            ->available()
            ->select(Job::PUBLIC_COLUMNS)
            ->with([
                'company:id,name,industry,website,logo_path,description,culture,benefits,locations,contact_email,owner_user_id',
                'company.owner:id,name,email',
                'category:id,name',
                'skills' => fn ($q) => $q->select('skills.id', 'skills.name', 'skills.type'),
            ]);

        if ($hideIrrelevant && ! empty($hiddenJobIds)) {
            $query->whereNotIn('jobs.id', $hiddenJobIds);
        }

        if (! $includeApplied && ! empty($appliedJobIds)) {
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

        $jobs->getCollection()->each(function ($job) use ($candidate, $appliedJobIds, $candidateSkillIds) {
            $matchingSkills = $candidateSkillIds
                ? $job->skills->whereIn('id', $candidateSkillIds)->count()
                : 0;

            $reasons = [];
            if ($matchingSkills > 0) {
                $reasons[] = "Matches {$matchingSkills} of your skills";
            }
            if ($candidate->location && $job->location && stripos($job->location, $candidate->location) !== false) {
                $reasons[] = 'Matches your preferred location';
            }
            if ($candidate->years_experience !== null && $job->experience_years !== null
                && $candidate->years_experience >= $job->experience_years) {
                $reasons[] = 'Matches your experience level';
            }

            $job->why_it_fits = $reasons ?: ['Matches current job discovery criteria'];
            $job->applied_by_me = in_array($job->id, $appliedJobIds, true);
        });

        return JobResource::collection($jobs)->additional([
            'hide_irrelevant' => $hideIrrelevant,
            'include_applied' => $includeApplied,
        ]);
    }
}
