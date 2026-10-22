<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Public listing representation of a job with truncated description and safe relations.
 */
class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => Str::limit((string) $this->description, 200),
            'employment_type' => $this->employment_type,
            'work_mode'       => $this->work_mode,
            'location'        => $this->location,
            'salary'          => [
                'min'      => $this->salary_min,
                'max'      => $this->salary_max,
                'currency' => $this->currency,
                'period'   => $this->salary_period,
            ],
            'experience_years' => $this->experience_years,
            'deadline'        => $this->deadline?->toIso8601String(),
            'published_at'    => $this->published_at?->toIso8601String(),
            'company'         => $this->whenLoaded('company', fn () => [
                'id'        => $this->company->id,
                'name'      => $this->company->name,
                'industry'  => $this->company->industry,
                'website'   => $this->company->website,
                'logo_path' => $this->company->logo_path,
            ]),
            'category'        => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'skills'          => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'type'        => $s->type,
                'is_required' => (bool) $s->pivot->is_required,
            ])->values()),
        ];
    }
}
