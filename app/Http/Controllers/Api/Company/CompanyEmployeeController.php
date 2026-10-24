<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\ApproveEmployeeRequest;
use App\Http\Requests\Company\RejectEmployeeRequest;
use App\Http\Requests\Company\StoreJoinRequest;
use App\Http\Resources\CompanyEmployeeResource;
use App\Models\CompanyEmployee;
use App\Services\CompanyEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyEmployeeController extends Controller
{
    public function __construct(
        protected CompanyEmployeeService $employeeService
    ) {}

    /**
     * POST /api/company/join-requests
     */
    public function storeJoinRequest(StoreJoinRequest $request): JsonResponse
    {
        $employee = $this->employeeService->createJoinRequest(
            data: $request->validated(),
            user: $request->user()
        );

        return response()->json([
            'message'  => 'Join request submitted successfully. Awaiting company review.',
            'employee' => new CompanyEmployeeResource($employee),
        ], 201);
    }

    /**
     * GET /api/company/employees?status=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $request->user()->resolveCompanyId();

        $employees = $this->employeeService->list(
            companyId: (int) $companyId,
            filters: $request->only(['status']),
            perPage: (int) $request->input('per_page', 15)
        );

        return CompanyEmployeeResource::collection($employees);
    }

    /**
     * GET /api/company/employees/{id}
     */
    public function show(Request $request, int $id): CompanyEmployeeResource
    {
        $userCompanyId = $request->user()->resolveCompanyId();

        $employee = CompanyEmployee::with(['user', 'role', 'reviewer', 'company'])->findOrFail($id);

        if ($request->user()->account_type !== 'admin' && (int) $employee->company_id !== (int) $userCompanyId) {
            abort(403, 'You do not have permission to view employees belonging to another company.');
        }

        return new CompanyEmployeeResource($employee);
    }

    /**
     * POST /api/company/employees/{id}/approve
     */
    public function approve(ApproveEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = CompanyEmployee::with('company')->findOrFail($id);
        $roleId = $request->validated()['role_id'];

        $updated = $this->employeeService->approve(
            employee: $employee,
            roleId: (int) $roleId,
            reviewer: $request->user()
        );

        return response()->json([
            'message'  => 'Employee join request approved successfully.',
            'employee' => new CompanyEmployeeResource($updated),
        ], 200);
    }

    /**
     * POST /api/company/employees/{id}/reject
     */
    public function reject(RejectEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = CompanyEmployee::with('company')->findOrFail($id);
        $reason = $request->validated()['rejection_reason'];

        $updated = $this->employeeService->reject(
            employee: $employee,
            reason: $reason,
            reviewer: $request->user()
        );

        return response()->json([
            'message'  => 'Employee join request rejected.',
            'employee' => new CompanyEmployeeResource($updated),
        ], 200);
    }

    /**
     * DELETE /api/company/employees/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $employee = CompanyEmployee::with('company')->findOrFail($id);

        $updated = $this->employeeService->remove(
            employee: $employee,
            reviewer: $request->user()
        );

        return response()->json([
            'message'  => 'Employee removed successfully.',
            'employee' => new CompanyEmployeeResource($updated),
        ], 200);
    }
}

