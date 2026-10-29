<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public company page representation. Pending, rejected, and suspended companies
 * are never passed to this resource by the public controllers.
 *
 * @mixin \App\Models\Company
 */
class CompanyPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $team = [];
        if ($this->relationLoaded('owner') && $this->owner) {
            $team[] = [
                'user_id' => $this->owner->id,
                'name' => $this->owner->name,
                'role' => 'owner',
            ];
        }

        if ($this->relationLoaded('companyEmployees')) {
            foreach ($this->companyEmployees as $employee) {
                if ($employee->user) {
                    $team[] = [
                        'user_id' => $employee->user->id,
                        'name' => $employee->user->name,
                        'role' => $employee->role?->name ?? $employee->requested_job_title,
                    ];
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'website' => $this->website,
            'industry' => $this->industry,
            'logo_path' => $this->logo_path,
            'description' => $this->description,
            'culture' => $this->culture,
            'benefits' => $this->benefits ?? [],
            'locations' => $this->locations ?? [],
            'open_roles_count' => (int) ($this->open_roles_count ?? 0),
            'hiring_team' => $team,
            'social_links' => $this->whenLoaded('companySocialLinks', fn () => $this->companySocialLinks->map(fn ($link) => [
                'platform' => $link->platform,
                'url' => $link->url,
            ])->values()),
        ];
    }
}
