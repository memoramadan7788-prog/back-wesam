<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CandidateApplicationDraft
 */
class ApplicationDraftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'job_title' => $this->job?->title,
            'cv_id' => $this->cv_id,
            'answers' => $this->answers ?? [],
            'expected_salary' => $this->expected_salary,
            'portfolio_url' => $this->portfolio_url,
            'cover_note' => $this->cover_note,
            'keep_in_talent_pool' => (bool) $this->keep_in_talent_pool,
            'consent' => (bool) $this->consent,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
