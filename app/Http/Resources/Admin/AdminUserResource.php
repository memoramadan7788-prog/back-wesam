<?php

namespace App\Http\Resources\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'account_type'      => $this->account_type,
            'account_status_id' => $this->account_status_id,
            'account_status'    => $this->accountStatus?->name,
            'roles'             => $this->roles->map(fn ($role) => [
                'id'          => $role->id,
                'name'        => $role->name,
                'scope'       => $role->scope,
                'company_id'  => $role->pivot?->company_id,
                'assigned_at' => $role->pivot?->assigned_at ? Carbon::parse($role->pivot->assigned_at)->toIso8601String() : null,
            ]),
            'permissions'       => $this->getAllPermissions(),
            'candidate_profile' => $this->whenLoaded('candidateProfile', fn () => $this->candidateProfile ? [
                'id'       => $this->candidateProfile->id,
                'headline' => $this->candidateProfile->headline,
                'phone'    => $this->candidateProfile->phone,
            ] : null),
            'owned_companies'   => $this->whenLoaded('ownedCompanies', fn () => $this->ownedCompanies->map(fn ($c) => [
                'id'     => $c->id,
                'name'   => $c->name,
                'status' => $c->status,
            ])),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}

