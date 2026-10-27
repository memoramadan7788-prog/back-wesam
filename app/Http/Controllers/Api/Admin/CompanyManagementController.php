<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectCompanyRequest;
use App\Http\Resources\Admin\AdminCompanyResource;
use App\Models\Company;
use App\Models\Admin;
use App\Services\AdminCompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyManagementController extends Controller
{
    public function __construct(
        protected AdminCompanyService $companyService
    ) {}

    /**
     * GET /api/admin/companies?status=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $companies = $this->companyService->list(
            filters: $request->only(['status', 'search']),
            perPage: (int) $request->input('per_page', 15)
        );

        return AdminCompanyResource::collection($companies);
    }

    /**
     * GET /api/admin/companies/{id}
     */
    public function show(int $id): AdminCompanyResource
    {
        $company = Company::with(['owner', 'reviewer', 'reviewerAdmin'])->withCount(['jobs', 'companyEmployees'])->findOrFail($id);

        return new AdminCompanyResource($company);
    }

    /**
     * POST /api/admin/companies/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $actor = $request->user();
        $updated = $this->companyService->approve(
            $company,
            $actor instanceof Admin ? 0 : $actor->id,
            $actor instanceof Admin ? $actor->id : null
        );

        return response()->json([
            'message' => 'Company registration approved successfully.',
            'company' => new AdminCompanyResource($updated),
        ], 200);
    }

    /**
     * POST /api/admin/companies/{id}/reject
     */
    public function reject(RejectCompanyRequest $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $reason = $request->validated()['rejection_reason'];
        $actor = $request->user();
        $updated = $this->companyService->reject(
            $company,
            $actor instanceof Admin ? 0 : $actor->id,
            $reason,
            $actor instanceof Admin ? $actor->id : null
        );

        return response()->json([
            'message' => 'Company registration rejected.',
            'company' => new AdminCompanyResource($updated),
        ], 200);
    }

    /**
     * POST /api/admin/companies/{id}/suspend
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $reason = $request->input('reason');
        $actor = $request->user();
        $updated = $this->companyService->suspend(
            $company,
            $actor instanceof Admin ? 0 : $actor->id,
            $reason,
            $actor instanceof Admin ? $actor->id : null
        );

        return response()->json([
            'message' => 'Company has been suspended.',
            'company' => new AdminCompanyResource($updated),
        ], 200);
    }
}

