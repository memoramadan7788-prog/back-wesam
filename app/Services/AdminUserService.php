<?php

namespace App\Services;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminUserService
{
    /**
     * List users with optional filtering.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with(['accountStatus', 'roles', 'candidateProfile', 'ownedCompanies'])
            ->when(!empty($filters['account_type']), fn ($q) => $q->where('account_type', $filters['account_type']))
            ->when(!empty($filters['account_status_id']), fn ($q) => $q->where('account_status_id', $filters['account_status_id']))
            ->when(!empty($filters['search']), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Update user account status (e.g. activate, block, deactivate).
     */
    public function updateStatus(User $user, int $newStatusId, int $adminId, ?string $reason = null): User
    {
        return DB::transaction(function () use ($user, $newStatusId, $adminId, $reason) {
            $oldStatusId = $user->account_status_id;

            $user->update([
                'account_status_id' => $newStatusId,
            ]);

            // When user account is deactivated, blocked, or deleted, revoke all active Sanctum tokens
            if ($newStatusId !== AccountStatus::ACTIVE) {
                $user->tokens()->delete();
            }

            AuditLog::record(
                action: 'user.status_changed',
                subject: $user,
                userId: $adminId,
                oldValues: ['account_status_id' => $oldStatusId],
                newValues: ['account_status_id' => $newStatusId],
                reason: $reason ?? "User account status changed from {$oldStatusId} to {$newStatusId}."
            );

            return $user->fresh(['accountStatus', 'roles', 'candidateProfile', 'ownedCompanies']);
        });
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(User $user, int $roleId, int $adminId, ?int $companyId = null): User
    {
        return DB::transaction(function () use ($user, $roleId, $adminId, $companyId) {
            $user->roles()->syncWithoutDetaching([
                $roleId => [
                    'company_id'  => $companyId,
                    'assigned_by' => $adminId,
                    'assigned_at' => now(),
                ],
            ]);

            $role = Role::find($roleId);

            AuditLog::record(
                action: 'user.role_assigned',
                subject: $user,
                userId: $adminId,
                oldValues: null,
                newValues: ['role_id' => $roleId, 'role_name' => $role?->name, 'company_id' => $companyId],
                reason: "Role '{$role?->name}' assigned to user."
            );

            return $user->fresh(['roles.permissions', 'accountStatus']);
        });
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(User $user, int $roleId, int $adminId): User
    {
        return DB::transaction(function () use ($user, $roleId, $adminId) {
            $role = Role::find($roleId);
            $user->roles()->detach($roleId);

            AuditLog::record(
                action: 'user.role_removed',
                subject: $user,
                userId: $adminId,
                oldValues: ['role_id' => $roleId, 'role_name' => $role?->name],
                newValues: null,
                reason: "Role '{$role?->name}' removed from user."
            );

            return $user->fresh(['roles.permissions', 'accountStatus']);
        });
    }
}

