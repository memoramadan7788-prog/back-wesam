<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobIndexRequest;
use App\Http\Resources\JobDetailResource;
use App\Http\Resources\JobResource;
use App\Models\Job;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobController extends Controller
{
    /**
     * GET /api/jobs
     * List available public jobs with filtering, sorting, and pagination.
     */
    public function index(JobIndexRequest $request): AnonymousResourceCollection
    {
        $f = $request->validated();

        $sortColumn = Job::SORTABLE[$f['sort_by'] ?? 'published_at'];
        $sortDir    = $f['sort_dir'] ?? 'desc';

        $jobs = Job::query()
            ->available()
            ->select(Job::PUBLIC_COLUMNS)
            ->with($this->relations())
            ->filter($f)
            ->orderBy($sortColumn, $sortDir)
            ->orderBy('jobs.id', $sortDir)
            ->paginate($f['per_page'] ?? 15)
            ->withQueryString();

        return JobResource::collection($jobs);
    }

    /**
     * GET /api/jobs/{job}
     * Retrieve single available job details.
     */
    public function show(int $job): JobDetailResource
    {
        $found = Job::query()
            ->available()
            ->select(Job::PUBLIC_COLUMNS)
            ->with($this->relations())
            ->where('jobs.id', $job)
            ->first();

        abort_if($found === null, 404, 'Job not found.');

        return new JobDetailResource($found);
    }

    /**
     * Publicly visible relations (sensitive attributes excluded).
     */
    private function relations(): array
    {
        return [
            'company:id,name,industry,website,logo_path',
            'category:id,name',
            'skills' => fn ($q) => $q->select('skills.id', 'skills.name', 'skills.type'),
        ];
    }
}
