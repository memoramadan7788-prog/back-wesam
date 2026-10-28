<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Company
 */
class AdminCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'website'           => $this->website,
            'industry'          => $this->industry,
            'contact_email'     => $this->contact_email,
            'contact_phone'     => $this->contact_phone,
            'address'           => $this->address,
            'status'            => $this->status,
            'account_status_id' => $this->account_status_id,
            'rejection_reason'  => $this->rejection_reason,
            'owner'             => $this->whenLoaded('owner', fn () => [
                'id'    => $this->owner->id,
                'name'  => $this->owner->name,
                'email' => $this->owner->email,
            ]),
            'reviewer'          => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id'    => $this->reviewer->id,
                'name'  => $this->reviewer->name,
                'email' => $this->reviewer->email,
            ] : ($this->whenLoaded('reviewerAdmin', fn () => $this->reviewerAdmin ? [
                'id'    => $this->reviewerAdmin->id,
                'name'  => $this->reviewerAdmin->name,
                'email' => $this->reviewerAdmin->email,
            ] : null))),
            'reviewed_at'       => $this->reviewed_at?->toIso8601String(),
            'jobs_count'        => $this->jobs_count ?? $this->jobs()->count(),
            'employees_count'   => $this->company_employees_count ?? $this->companyEmployees()->count(),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}

