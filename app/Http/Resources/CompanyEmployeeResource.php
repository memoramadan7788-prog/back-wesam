<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CompanyEmployee
 */
class CompanyEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'company_id'           => $this->company_id,
            'company_name'         => $this->company?->name,
            'user'                 => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'requested_job_title'  => $this->requested_job_title,
            'requested_department' => $this->requested_department,
            'status'               => $this->status,
            'role'                 => $this->whenLoaded('role', fn () => $this->role ? [
                'id'    => $this->role->id,
                'name'  => $this->role->name,
                'scope' => $this->role->scope,
            ] : null),
            'reviewer'             => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id'    => $this->reviewer->id,
                'name'  => $this->reviewer->name,
                'email' => $this->reviewer->email,
            ] : null),
            'reviewed_at'          => $this->reviewed_at?->toIso8601String(),
            'rejection_reason'     => $this->rejection_reason,
            'requested_at'         => $this->requested_at?->toIso8601String(),
            'joined_at'            => $this->joined_at?->toIso8601String(),
            'left_at'              => $this->left_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}

