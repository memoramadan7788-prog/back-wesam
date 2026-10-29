<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobIndexRequest;
use App\Http\Resources\JobDetailResource;
use App\Http\Resources\JobResource;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Http\Request;
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

        $baseQuery = Job::query()
            ->available()
            ->filter($f);

        $totalCount = (clone $baseQuery)->count();
        $facets = $this->facets($baseQuery);

        $jobs = (clone $baseQuery)
            ->select(Job::PUBLIC_COLUMNS)
            ->with($this->relations())
            ->orderBy($sortColumn, $sortDir)
            ->orderBy('jobs.id', $sortDir)
            ->paginate($f['per_page'] ?? 15)
            ->withQueryString();

        $this->decorateCandidateState($request, $jobs->getCollection());

        return JobResource::collection($jobs)->additional([
            'total_count' => $totalCount,
            'facets' => $facets,
        ]);
    }

    /**
     * GET /api/jobs/{job}
     * Retrieve single available job details.
     */
    public function show(Request $request, int $job): JobDetailResource
    {
        $found = Job::query()
            ->available()
            ->select(Job::PUBLIC_COLUMNS)
            ->with($this->relations())
            ->where('jobs.id', $job)
            ->first();

        abort_if($found === null, 404, 'Job not found.');

        $this->decorateCandidateState($request, collect([$found]));

        return new JobDetailResource($found);
    }

    /**
     * Publicly visible relations (sensitive attributes excluded).
     */
    private function relations(): array
    {
        return [
            'company:id,name,industry,website,logo_path,description,culture,benefits,locations,contact_email,owner_user_id',
            'company.owner:id,name,email',
            'category:id,name',
            'skills' => fn ($q) => $q->select('skills.id', 'skills.name', 'skills.type'),
        ];
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

    private function facets($baseQuery): array
    {
        $counts = function (string $column) use ($baseQuery): array {
            return (clone $baseQuery)
                ->whereNotNull($column)
                ->select($column)
                ->selectRaw('COUNT(*) as count')
                ->groupBy($column)
                ->orderBy($column)
                ->get()
                ->map(fn ($row) => ['value' => $row->{$column}, 'count' => (int) $row->count])
                ->values()
                ->all();
        };

        $categoryCounts = (clone $baseQuery)
            ->whereNotNull('category_id')
            ->select('category_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category_id')
            ->get();

        $categoryNames = \App\Models\JobCategory::whereIn('id', $categoryCounts->pluck('category_id'))
            ->pluck('name', 'id');

        return [
            'employment_type' => $counts('employment_type'),
            'work_mode' => $counts('work_mode'),
            'category' => $categoryCounts->map(fn ($row) => [
                'value' => (int) $row->category_id,
                'label' => $categoryNames[$row->category_id] ?? null,
                'count' => (int) $row->count,
            ])->values()->all(),
        ];
    }
}
