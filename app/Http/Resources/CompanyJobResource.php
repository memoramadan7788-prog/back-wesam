<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Job
 */
class CompanyJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'company_id'         => $this->company_id,
            'company_name'       => $this->company?->name,
            'category_id'        => $this->category_id,
            'category_name'      => $this->category?->name,
            'title'              => $this->title,
            'requisition_code'   => $this->requisition_code,
            'hiring_model'       => $this->hiring_model,
            'description'        => $this->description,
            'requirements'       => $this->requirements,
            'benefits'           => $this->benefits ?? [],
            'location'           => $this->location,
            'work_mode'          => $this->work_mode,
            'employment_type'    => $this->employment_type,
            'salary_min'         => $this->salary_min,
            'salary_max'         => $this->salary_max,
            'currency'           => $this->currency,
            'salary_period'      => $this->salary_period,
            'experience_years'   => $this->experience_years,
            'department'         => $this->department,
            'openings_count'     => $this->openings_count,
            'deadline'           => $this->deadline?->toIso8601String(),
            'visibility'         => $this->visibility,
            'private_apply_mode' => $this->private_apply_mode,
            'status'             => $this->status,
            'allow_agency_submissions' => (bool) $this->allow_agency_submissions,
            'publish_to_search' => (bool) $this->publish_to_search,
            'requisition_owner' => $this->whenLoaded('requisitionOwner', fn () => $this->requisitionOwner ? ['id' => $this->requisitionOwner->id, 'name' => $this->requisitionOwner->name] : null),
            'hiring_manager' => $this->whenLoaded('hiringManager', fn () => $this->hiringManager ? ['id' => $this->hiringManager->id, 'name' => $this->hiringManager->name] : null),
            'published_at'       => $this->published_at?->toIso8601String(),
            'closed_at'          => $this->closed_at?->toIso8601String(),
            'applications_count' => $this->applications_count ?? $this->applications()->count(),
            'skills'             => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
            ])),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}

