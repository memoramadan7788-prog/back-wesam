<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\ChangeJobStatusRequest;
use App\Http\Requests\Company\StoreJobRequest;
use App\Http\Requests\Company\UpdateJobRequest;
use App\Http\Resources\CompanyJobResource;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CompanyJobController extends Controller
{
    /**
     * GET /api/company/jobs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $request->query('company_id');

        $jobs = Job::with(['company', 'category', 'skills'])
            ->withCount('applications')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('created_at')
            ->paginate(15);

        return CompanyJobResource::collection($jobs);
    }

    /**
     * POST /api/company/jobs
     */
    public function store(StoreJobRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $skills = $validated['skills'] ?? [];
        unset($validated['skills']);

        $company = \App\Models\Company::findOrFail($validated['company_id']);
        if ($company->status === 'pending') {
            return response()->json([
                'message' => 'Your company account is pending administrative approval. You cannot publish jobs until your account is approved.',
            ], 403);
        }

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'published';
        if ($validated['status'] === 'published') {
            $validated['published_at'] = now();
        }

        $syncSkills = [];
        foreach ($skills as $skill) {
            if (is_array($skill)) {
                $skillId = $skill['id'] ?? $skill['skill_id'] ?? null;
                if ($skillId) {
                    $syncSkills[$skillId] = ['is_required' => $skill['is_required'] ?? true];
                }
            } else {
                $syncSkills[$skill] = ['is_required' => true];
            }
        }

        $job = DB::transaction(function () use ($validated, $syncSkills) {
            $job = Job::create($validated);
            if (! empty($syncSkills)) {
                $job->skills()->sync($syncSkills);
            }
            return $job;
        });

        return response()->json([
            'message' => 'Job posted successfully.',
            'job'     => new CompanyJobResource($job->load(['company', 'category', 'skills'])),
        ], 201);
    }

    /**
     * GET /api/company/jobs/{id}
     */
    public function show(Request $request, int $id): CompanyJobResource
    {
        $job = Job::with(['company', 'category', 'skills'])
            ->withCount('applications')
            ->findOrFail($id);

        return new CompanyJobResource($job);
    }

    /**
     * PUT /api/company/jobs/{id}
     */
    public function update(UpdateJobRequest $request, int $id): JsonResponse
    {
        $job = Job::findOrFail($id);
        $validated = $request->validated();

        $skills = $validated['skills'] ?? null;
        unset($validated['skills']);

        DB::transaction(function () use ($job, $validated, $skills) {
            $job->update($validated);
            if ($skills !== null) {
                $job->skills()->sync($skills);
            }
        });

        return response()->json([
            'message' => 'Job updated successfully.',
            'job'     => new CompanyJobResource($job->fresh(['company', 'category', 'skills'])),
        ], 200);
    }

    /**
     * PATCH /api/company/jobs/{id}/status
     */
    public function changeStatus(ChangeJobStatusRequest $request, int $id): JsonResponse
    {
        $job = Job::findOrFail($id);
        $status = $request->validated()['status'];

        $updates = ['status' => $status];
        if ($status === 'published' && ! $job->published_at) {
            $updates['published_at'] = now();
        } elseif ($status === 'closed') {
            $updates['closed_at'] = now();
        }

        $job->update($updates);

        return response()->json([
            'message' => "Job status updated to {$status}.",
            'job'     => new CompanyJobResource($job->fresh(['company', 'category', 'skills'])),
        ], 200);
    }

    /**
     * DELETE /api/company/jobs/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $job = Job::findOrFail($id);
        $job->delete();

        return response()->json([
            'message' => 'Job deleted successfully.',
        ], 200);
    }
}

