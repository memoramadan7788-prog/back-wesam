<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyPublicResource;
use App\Http\Resources\JobResource;
use App\Models\Company;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicFeaturedController extends Controller
{
    /** GET /api/public/featured */
    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->where('status', 'approved')
            ->with(['owner:id,name', 'companySocialLinks'])
            ->withCount(['jobs as open_roles_count' => fn ($query) => $query->available()])
            ->orderByDesc('open_roles_count')
            ->orderBy('name')
            ->limit(6)
            ->get();

        $jobs = Job::query()
            ->available()
            ->select(Job::PUBLIC_COLUMNS)
            ->with([
                'company:id,name,industry,website,logo_path,description,culture,benefits,locations,contact_email,owner_user_id',
                'company.owner:id,name,email',
                'category:id,name',
                'skills' => fn ($query) => $query->select('skills.id', 'skills.name', 'skills.type'),
            ])
            ->latest('published_at')
            ->limit(6)
            ->get();

        $this->decorateCandidateState($request, $jobs);

        return response()->json([
            'data' => [
                'featured_companies' => CompanyPublicResource::collection($companies)->resolve(),
                'featured_jobs' => JobResource::collection($jobs)->resolve(),
                'counts' => [
                    'companies' => Company::where('status', 'approved')->count(),
                    'jobs' => Job::available()->count(),
                ],
            ],
        ], 200);
    }

    private function decorateCandidateState(Request $request, $jobs): void
    {
        $candidate = $request->user()?->candidateProfile;
        if (! $candidate) {
            return;
        }

        $savedIds = $candidate->savedJobs()->pluck('jobs.id')->all();
        $appliedIds = $candidate->applications()->pluck('job_id')->all();
        foreach ($jobs as $job) {
            $job->saved_by_me = in_array($job->id, $savedIds, true);
            $job->applied_by_me = in_array($job->id, $appliedIds, true);
        }
    }
}
