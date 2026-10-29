<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Application
 */
class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'job_id'       => $this->job_id,
            'job_title'    => $this->job?->title,
            'company_name' => $this->job?->company?->name,
            'candidate_id' => $this->candidate_id,
            'candidate'    => $this->whenLoaded('candidateProfile', fn () => [
                'id'       => $this->candidateProfile->id,
                'name'     => $this->candidateProfile->user?->name,
                'email'    => $this->candidateProfile->user?->email,
                'headline' => $this->candidateProfile->headline,
            ]),
            'status'       => $this->status,
            'stage_label'  => ['submitted' => 'Applied', 'screening' => 'Screening', 'shortlisted' => 'Screening', 'interviewing' => 'Interview', 'offered' => 'Offer', 'hired' => 'Hired', 'rejected' => 'Rejected'][$this->status] ?? $this->status,
            'source'       => $this->source,
            'owner_id'     => $this->owner_id,
            'attempt_no'   => $this->attempt_no,
            'applied_at'   => $this->applied_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
        ];
    }
}

