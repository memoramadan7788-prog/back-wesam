<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Application
 */
class ApplicationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'job_id'       => $this->job_id,
            'job'          => [
                'id'       => $this->job?->id,
                'title'    => $this->job?->title,
                'company'  => $this->job?->company?->name,
                'location' => $this->job?->location,
            ],
            'candidate_id' => $this->candidate_id,
            'candidate'    => [
                'id'               => $this->candidateProfile?->id,
                'name'             => $this->candidateProfile?->user?->name,
                'email'            => $this->candidateProfile?->user?->email,
                'phone'            => $this->candidateProfile?->phone,
                'headline'         => $this->candidateProfile?->headline,
                'years_experience' => $this->candidateProfile?->years_experience,
            ],
            'cv'           => $this->candidateCv ? [
                'id'                => $this->candidateCv->id,
                'version'           => $this->candidateCv->version,
                'original_filename' => $this->candidateCv->original_filename,
            ] : null,
            'cover_note'   => $this->cover_note,
            'status'       => $this->status,
            'attempt_no'   => $this->attempt_no,
            'history'      => $this->whenLoaded('applicationStatusHistories', fn () => $this->applicationStatusHistories->map(fn ($h) => [
                'from'       => $h->from_status,
                'to'         => $h->to_status,
                'changed_by' => $h->changer?->name,
                'reason'     => $h->reason,
                'comment'    => $h->reason,
                'changed_at' => $h->created_at?->toIso8601String(),
            ])),
            'applied_at'   => $this->applied_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
        ];
    }
}

