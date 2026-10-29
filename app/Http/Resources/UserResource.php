<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $company = $this->ownedCompanies?->first() ?? $this->companyEmployee?->company;

        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'account_type'       => $this->account_type,
            'account_status_id'  => $this->account_status_id,
            'account_status'     => $this->accountStatus?->name,
            'locale'             => $this->locale,
            'theme'              => $this->theme,
            'email_verified_at'  => $this->email_verified_at?->toIso8601String(),
            'roles'              => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions'        => $this->whenLoaded('roles', fn () => $this->getAllPermissions()),
            'company'            => $company ? [
                'id'     => $company->id,
                'name'   => $company->name,
                'status' => $company->status,
            ] : null,
            'candidate_profile'  => $this->whenLoaded('candidateProfile', fn () => [
                'id'               => $this->candidateProfile->id,
                'headline'         => $this->candidateProfile->headline,
                'phone'            => $this->candidateProfile->phone,
                'years_experience' => $this->candidateProfile->years_experience,
            ]),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
