<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CompanyEmployeeService
{
    /**
     * Submit a join request to a company.
     * Enforces: One user = One company (unique user_id constraint).
     */
    public function createJoinRequest(array $data, User $user): CompanyEmployee
    {
        $existing = CompanyEmployee::where('user_id', $user->id)->first();

        if ($existing && in_array($existing->status, ['pending', 'approved'])) {
            abort(409, 'You have already submitted a join request or are already an employee of a company.');
        }

        $company = Company::findOrFail($data['company_id']);

        if ($company->status !== 'approved') {
            abort(403, 'Cannot request to join a company that is not approved.');
        }

        return DB::transaction(function () use ($data, $user, $company, $existing) {
            if ($existing) {
                $oldCompanyId = $existing->company_id;
                $oldStatus = $existing->status;

                $existing->update([
                    'company_id'           => $company->id,
                    'requested_job_title'  => $data['requested_job_title'],
                    'requested_department' => $data['requested_department'] ?? null,
                    'status'               => 'pending',
                    'role_id'              => null,
                    'reviewed_by'          => null,
                    'reviewed_at'          => null,
                    'rejection_reason'     => null,
                    'requested_at'         => now(),
                    'joined_at'            => null,
                    'left_at'              => null,
                ]);

                AuditLog::record(
                    action: 'employee.join_re_requested',
                    subject: $existing,
                    userId: $user->id,
                    oldValues: [
                        'company_id' => $oldCompanyId,
                        'status'     => $oldStatus,
                    ],
                    newValues: [
                        'company_id'          => $company->id,
                        'requested_job_title' => $data['requested_job_title'],
                        'status'              => 'pending',
                    ],
                    reason: 'User submitted a new join request after previous rejection/removal.'
                );

                return $existing->fresh(['company', 'user']);
            }

            $employee = CompanyEmployee::create([
                'company_id'           => $company->id,
                'user_id'              => $user->id,
                'requested_job_title'  => $data['requested_job_title'],
                'requested_department' => $data['requested_department'] ?? null,
                'status'               => 'pending',
                'requested_at'         => now(),
            ]);

            AuditLog::record(
                action: 'employee.join_requested',
                subject: $employee,
                userId: $user->id,
                oldValues: null,
                newValues: [
                    'company_id'          => $company->id,
                    'requested_job_title' => $data['requested_job_title'],
                ],
                reason: 'User submitted a company join request.'
            );

            return $employee->fresh(['company', 'user']);
        });
    }

    /**
     * List employees of a company with optional status filter.
     */
    public function list(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CompanyEmployee::query()
            ->with(['user', 'role', 'reviewer', 'company'])
            ->where('company_id', $companyId)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Update employee details (requested_job_title, requested_department).
     */
    public function update(CompanyEmployee $employee, array $data, User $user): CompanyEmployee
    {
        $userCompanyId = $user->resolveCompanyId();

        if ($user->account_type !== 'admin' && (int) $employee->company_id !== (int) $userCompanyId) {
            abort(403, 'You do not have permission to manage employees belonging to another company.');
        }

        if (in_array($employee->status, ['rejected', 'removed'])) {
            abort(409, "Cannot update employee details with status '{$employee->status}'.");
        }

        return DB::transaction(function () use ($employee, $data, $user) {
            $oldValues = [
                'requested_job_title'  => $employee->requested_job_title,
                'requested_department' => $employee->requested_department,
            ];

            $employee->update(array_filter([
                'requested_job_title'  => $data['requested_job_title'] ?? $employee->requested_job_title,
                'requested_department' => array_key_exists('requested_department', $data) ? $data['requested_department'] : $employee->requested_department,
            ], fn ($value) => $value !== null || array_key_exists('requested_department', $data)));

            AuditLog::record(
                action: 'employee.updated',
                subject: $employee,
                userId: $user->id,
                oldValues: $oldValues,
                newValues: [
                    'requested_job_title'  => $employee->requested_job_title,
                    'requested_department' => $employee->requested_department,
                ],
                reason: 'Company employee details updated.'
            );

            return $employee->fresh(['user', 'role', 'reviewer', 'company']);
        });
    }

    /**
     * Approve a pending employee join request and assign a role.
     */
    public function approve(CompanyEmployee $employee, int $roleId, User $reviewer): CompanyEmployee
    {
        $reviewerCompanyId = $reviewer->resolveCompanyId();

        if ($reviewer->account_type !== 'admin' && (int) $employee->company_id !== (int) $reviewerCompanyId) {
            abort(403, 'You do not have permission to manage employees belonging to another company.');
        }

        if ($employee->status !== 'pending') {
            abort(409, "Cannot approve employee with status '{$employee->status}'. Only 'pending' requests can be approved.");
        }

        $role = Role::findOrFail($roleId);
        if (! $role->isCompanyRole()) {
            abort(422, 'Assigned role must have company scope.');
        }

        return DB::transaction(function () use ($employee, $role, $reviewer) {
            $oldValues = [
                'status'  => $employee->status,
                'role_id' => $employee->role_id,
            ];

            $employee->update([
                'status'      => 'approved',
                'role_id'     => $role->id,
                'joined_at'   => now(),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            // Assign role to user in user_roles pivot
            $employee->user->roles()->syncWithoutDetaching([
                $role->id => [
                    'company_id'  => $employee->company_id,
                    'assigned_by' => $reviewer->id,
                    'assigned_at' => now(),
                ],
            ]);

            // Send notification to the user
            Notification::create([
                'recipient_id'    => $employee->user_id,
                'channel'         => 'platform',
                'type'            => 'company.join_approved',
                'title'           => 'Join Request Approved',
                'body'            => "Your request to join {$employee->company?->name} has been approved as {$role->name}.",
                'related_type'    => Company::class,
                'related_id'      => $employee->company_id,
                'delivery_status' => 'delivered',
            ]);

            AuditLog::record(
                action: 'employee.approved',
                subject: $employee,
                userId: $reviewer->id,
                oldValues: $oldValues,
                newValues: [
                    'status'      => 'approved',
                    'role_id'     => $role->id,
                    'reviewed_by' => $reviewer->id,
                ],
                reason: "Employee join request approved with role '{$role->name}'."
            );

            return $employee->fresh(['user', 'role', 'reviewer', 'company']);
        });
    }

    /**
     * Reject a pending employee join request.
     */
    public function reject(CompanyEmployee $employee, string $reason, User $reviewer): CompanyEmployee
    {
        $reviewerCompanyId = $reviewer->resolveCompanyId();

        if ($reviewer->account_type !== 'admin' && (int) $employee->company_id !== (int) $reviewerCompanyId) {
            abort(403, 'You do not have permission to manage employees belonging to another company.');
        }

        if ($employee->status !== 'pending') {
            abort(409, "Cannot reject employee with status '{$employee->status}'. Only 'pending' requests can be rejected.");
        }

        return DB::transaction(function () use ($employee, $reason, $reviewer) {
            $oldValues = [
                'status' => $employee->status,
            ];

            $employee->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by'      => $reviewer->id,
                'reviewed_at'      => now(),
            ]);

            AuditLog::record(
                action: 'employee.rejected',
                subject: $employee,
                userId: $reviewer->id,
                oldValues: $oldValues,
                newValues: [
                    'status'           => 'rejected',
                    'rejection_reason' => $reason,
                    'reviewed_by'      => $reviewer->id,
                ],
                reason: $reason
            );

            return $employee->fresh(['user', 'role', 'reviewer', 'company']);
        });
    }

    /**
     * Remove an employee from the company.
     * Enforces: Company admin cannot remove self. Company owner cannot be removed.
     */
    public function remove(CompanyEmployee $employee, User $reviewer): CompanyEmployee
    {
        $reviewerCompanyId = $reviewer->resolveCompanyId();

        if ($reviewer->account_type !== 'admin' && (int) $employee->company_id !== (int) $reviewerCompanyId) {
            abort(403, 'You do not have permission to manage employees belonging to another company.');
        }

        // Prevent removing the company owner
        $company = $employee->company;
        if ($company && (int) $employee->user_id === (int) $company->owner_user_id) {
            abort(403, 'The company owner cannot be removed from the company.');
        }

        // Prevent company admin removing self
        if ((int) $employee->user_id === (int) $reviewer->id) {
            abort(403, 'Company administrators cannot remove their own account from the company.');
        }

        return DB::transaction(function () use ($employee, $reviewer) {
            $oldRole = $employee->role_id;

            $employee->update([
                'status'  => 'removed',
                'left_at' => now(),
            ]);

            // Detach user roles for this company
            if ($employee->user) {
                $employee->user->roles()
                    ->wherePivot('company_id', $employee->company_id)
                    ->detach();
            }

            AuditLog::record(
                action: 'employee.removed',
                subject: $employee,
                userId: $reviewer->id,
                oldValues: ['status' => 'approved', 'role_id' => $oldRole],
                newValues: ['status' => 'removed'],
                reason: 'Employee removed from company.'
            );

            return $employee->fresh(['user', 'role', 'reviewer', 'company']);
        });
    }
}