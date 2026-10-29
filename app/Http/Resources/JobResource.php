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
            'posted_at'       => $this->published_at?->toIso8601String(),
            'requisition_code' => $this->requisition_code,
            'hiring_model'    => $this->hiring_model,
            'saved_by_me'     => (bool) ($this->resource->saved_by_me ?? false),
            'applied_by_me'   => (bool) ($this->resource->applied_by_me ?? false),
            'why_it_fits'     => $this->resource->why_it_fits ?? null,
            'updated_since_saved' => $this->resource->updated_since_saved ?? null,
            'updated_since_saved_reason' => $this->resource->updated_since_saved_reason ?? null,
            'company'         => $this->whenLoaded('company', fn () => [
                'id'        => $this->company->id,
                'name'      => $this->company->name,
                'industry'  => $this->company->industry,
                'website'   => $this->company->website,
                'logo_path' => $this->company->logo_path,
                'about'     => [
                    'description' => $this->company->description,
                    'culture' => $this->company->culture,
                    'benefits' => $this->company->benefits ?? [],
                    'locations' => $this->company->locations ?? [],
                ],
                'hiring_contact' => [
                    'name' => $this->company->owner?->name,
                    'email' => $this->company->contact_email,
                ],
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
