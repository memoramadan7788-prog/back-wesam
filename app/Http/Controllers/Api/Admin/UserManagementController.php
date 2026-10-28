<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignUserRoleRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Models\Admin;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserManagementController extends Controller
{
    public function __construct(
        protected AdminUserService $userService
    ) {}

    /**
     * GET /api/admin/users
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = $this->userService->list(
            filters: $request->only(['account_type', 'account_status_id', 'search']),
            perPage: (int) $request->input('per_page', 15)
        );

        return AdminUserResource::collection($users);
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function show(int $id): AdminUserResource
    {
        $user = User::with(['accountStatus', 'roles.permissions', 'candidateProfile', 'ownedCompanies'])->findOrFail($id);

        return new AdminUserResource($user);
    }

    /**
     * PATCH /api/admin/users/{id}/status
     */
    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();
        $actor = $request->user();

        $updated = $this->userService->updateStatus(
            user: $user,
            newStatusId: $validated['account_status_id'],
            adminId: $actor instanceof Admin ? null : $actor->id,
            reason: $validated['reason'] ?? null,
            adminActorId: $actor instanceof Admin ? $actor->id : null
        );

        return response()->json([
            'message' => 'User status updated successfully.',
            'user'    => new AdminUserResource($updated),
        ], 200);
    }

    /**
     * POST /api/admin/users/{id}/roles
     */
    public function assignRole(AssignUserRoleRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();
        $actor = $request->user();

        $updated = $this->userService->assignRole(
            user: $user,
            roleId: $validated['role_id'],
            adminId: $actor instanceof Admin ? null : $actor->id,
            companyId: $validated['company_id'] ?? null,
            adminActorId: $actor instanceof Admin ? $actor->id : null
        );

        return response()->json([
            'message' => 'Role assigned successfully.',
            'user'    => new AdminUserResource($updated),
        ], 200);
    }

    /**
     * DELETE /api/admin/users/{id}/roles/{roleId}
     */
    public function removeRole(Request $request, int $id, int $roleId): JsonResponse
    {
        $user = User::findOrFail($id);
        $actor = $request->user();

        $updated = $this->userService->removeRole(
            user: $user,
            roleId: $roleId,
            adminId: $actor instanceof Admin ? null : $actor->id,
            adminActorId: $actor instanceof Admin ? $actor->id : null
        );

        return response()->json([
            'message' => 'Role removed successfully.',
            'user'    => new AdminUserResource($updated),
        ], 200);
    }

    /**
     * GET /api/admin/roles
     */
    public function roles(): AnonymousResourceCollection
    {
        $roles = Role::with(['permissions', 'category'])->get();

        return RoleResource::collection($roles);
    }
}

