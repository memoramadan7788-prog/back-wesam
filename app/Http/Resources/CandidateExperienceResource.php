<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CandidateExperience
 */
class CandidateExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'company_name' => $this->company_name,
            'job_title'    => $this->job_title,
            'start_date'   => $this->start_date?->format('Y-m-d'),
            'end_date'     => $this->end_date?->format('Y-m-d'),
            'is_current'   => (bool) $this->is_current,
            'description'  => $this->description,
        ];
    }
}

