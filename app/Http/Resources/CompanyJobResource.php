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
            'description'        => $this->description,
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
            'status'             => $this->status,
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

