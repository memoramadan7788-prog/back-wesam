<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Admin;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminCompanyService
{
    /**
     * List companies with optional status filtering.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Company::query()
            ->with(['owner', 'reviewer', 'reviewerAdmin'])
            ->withCount(['jobs', 'companyEmployees'])
            ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['search']), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Approve a company registration.
     * Allowed transitions: pending -> approved, suspended -> approved.
     */
    public function approve(Company $company, int $reviewerId, ?int $reviewerAdminId = null): Company
    {
        if (! in_array($company->status, ['pending', 'suspended'], true)) {
            abort(409, "Cannot approve company with status '{$company->status}'. Allowed transitions: pending -> approved, suspended -> approved.");
        }

        return DB::transaction(function () use ($company, $reviewerId, $reviewerAdminId) {
            $oldValues = [
                'status'      => $company->status,
                'reviewed_by' => $company->reviewed_by,
                'reviewed_at' => $company->reviewed_at?->toIso8601String(),
            ];

            $company->update([
                'status'           => 'approved',
                'reviewed_by'      => $reviewerAdminId ? null : $reviewerId,
                'reviewed_by_admin_id' => $reviewerAdminId,
                'reviewed_at'      => now(),
                'rejection_reason' => null,
            ]);

            $newValues = [
                'status'      => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $company->reviewed_at->toIso8601String(),
            ];

            AuditLog::record(
                action: 'company.approved',
                subject: $company,
                userId: $reviewerId,
                oldValues: $oldValues,
                newValues: $newValues,
                reason: 'Company registration approved by administrator.',
                adminId: $reviewerAdminId
            );

            return $company->fresh(['owner', 'reviewer', 'reviewerAdmin']);
        });
    }

    /**
     * Reject a company registration.
     * Allowed transitions: pending -> rejected.
     */
    public function reject(Company $company, int $reviewerId, string $reason, ?int $reviewerAdminId = null): Company
    {
        if ($company->status !== 'pending') {
            abort(409, "Cannot reject company with status '{$company->status}'. Only companies with 'pending' status can be rejected.");
        }

        return DB::transaction(function () use ($company, $reviewerId, $reason, $reviewerAdminId) {
            $oldValues = [
                'status'           => $company->status,
                'reviewed_by'      => $company->reviewed_by,
                'reviewed_at'      => $company->reviewed_at?->toIso8601String(),
                'rejection_reason' => $company->rejection_reason,
            ];

            $company->update([
                'status'           => 'rejected',
                'reviewed_by'      => $reviewerAdminId ? null : $reviewerId,
                'reviewed_by_admin_id' => $reviewerAdminId,
                'reviewed_at'      => now(),
                'rejection_reason' => $reason,
            ]);

            $newValues = [
                'status'           => 'rejected',
                'reviewed_by'      => $reviewerId,
                'reviewed_at'      => $company->reviewed_at->toIso8601String(),
                'rejection_reason' => $reason,
            ];

            AuditLog::record(
                action: 'company.rejected',
                subject: $company,
                userId: $reviewerId,
                oldValues: $oldValues,
                newValues: $newValues,
                reason: $reason,
                adminId: $reviewerAdminId
            );

            return $company->fresh(['owner', 'reviewer', 'reviewerAdmin']);
        });
    }

    /**
     * Suspend a company account.
     * Allowed transitions: approved -> suspended.
     */
    public function suspend(Company $company, int $reviewerId, ?string $reason = null, ?int $reviewerAdminId = null): Company
    {
        if ($company->status !== 'approved') {
            abort(409, "Cannot suspend company with status '{$company->status}'. Only companies with 'approved' status can be suspended.");
        }

        return DB::transaction(function () use ($company, $reviewerId, $reason, $reviewerAdminId) {
            $oldValues = [
                'status'      => $company->status,
                'reviewed_by' => $company->reviewed_by,
                'reviewed_at' => $company->reviewed_at?->toIso8601String(),
            ];

            $company->update([
                'status'      => 'suspended',
                'reviewed_by' => $reviewerAdminId ? null : $reviewerId,
                'reviewed_by_admin_id' => $reviewerAdminId,
                'reviewed_at' => now(),
            ]);

            $newValues = [
                'status'      => 'suspended',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $company->reviewed_at->toIso8601String(),
            ];

            AuditLog::record(
                action: 'company.suspended',
                subject: $company,
                userId: $reviewerId,
                oldValues: $oldValues,
                newValues: $newValues,
                reason: $reason ?? 'Company suspended by administrator.',
                adminId: $reviewerAdminId
            );

            return $company->fresh(['owner', 'reviewer', 'reviewerAdmin']);
        });
    }
}

