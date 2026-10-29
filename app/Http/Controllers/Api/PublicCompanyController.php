<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyPublicResource;
use App\Http\Resources\JobResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicCompanyController extends Controller
{
    /** GET /api/companies */
    public function index(): AnonymousResourceCollection
    {
        $companies = Company::query()
            ->where('status', 'approved')
            ->with(['owner:id,name', 'companySocialLinks'])
            ->withCount(['jobs as open_roles_count' => fn ($query) => $query->available()])
            ->orderBy('name')
            ->paginate(15);

        return CompanyPublicResource::collection($companies);
    }

    /** GET /api/companies/{id} */
    public function show(int $id): CompanyPublicResource
    {
        $company = $this->publicCompany($id)->with([
            'owner:id,name',
            'companyEmployees' => fn ($query) => $query->where('status', 'approved')->with(['user:id,name', 'role:id,name']),
            'companySocialLinks',
        ])->withCount(['jobs as open_roles_count' => fn ($query) => $query->available()])->firstOrFail();

        return new CompanyPublicResource($company);
    }

    /** GET /api/companies/{id}/jobs */
    public function jobs(Request $request, int $id): AnonymousResourceCollection
    {
        $company = $this->publicCompany($id)->firstOrFail();
        $jobs = $company->jobs()
            ->available()
            ->select(\App\Models\Job::PUBLIC_COLUMNS)
            ->with([
                'company:id,name,industry,website,logo_path,description,culture,benefits,locations,contact_email,owner_user_id',
                'company.owner:id,name,email',
                'category:id,name',
                'skills' => fn ($query) => $query->select('skills.id', 'skills.name', 'skills.type'),
            ])
            ->orderByDesc('published_at')
            ->paginate(15)
            ->withQueryString();

        $this->decorateCandidateState($request, $jobs->getCollection());

        return JobResource::collection($jobs);
    }

    private function publicCompany(int $id)
    {
        return Company::query()->whereKey($id)->where('status', 'approved');
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
