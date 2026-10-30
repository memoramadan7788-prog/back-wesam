<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class CvMatchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $d = $this->details ?? [];

        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'status' => $this->run_status,           // pending | ok | failed
            'error' => $this->run_status === 'failed' ? $this->error_message : null,

            'score' => $this->score !== null ? (float) $this->score : null,
            'recommendation' => $d['recommendation'] ?? null,
            'score_breakdown' => $d['score_breakdown'] ?? null,

           
            'matched_skills' => $this->matched_skills,
            'missing_skills' => $this->missing_skills,
            'verified_strengths' => $d['strengths'] ?? [],

          
            'ai_opinion' => [
                'summary' => $this->summary,
                'experience_match' => $d['experience_match'] ?? null,
                'education_match' => $d['education_match'] ?? null,
                'weaknesses' => $d['weaknesses'] ?? [],
                'note' => 'AI-generated text. Not verified against the resume.',
            ],

            'meta' => [
                'model_version' => $this->model_version,
                'skills_source' => $d['skills_source'] ?? null,
                'cv_truncated' => $d['cv_truncated'] ?? null,
                'dropped_unverified_strengths' => $d['dropped_unverified_strengths'] ?? null,
                'attempts' => $d['attempts'] ?? null,
                'request_id' => $this->request_id,
            ],

            'review' => $this->whenLoaded('latestDecision', fn () => $this->latestDecision ? [
                'decision' => $this->latestDecision->decision,
                'reason' => $this->latestDecision->reason,
                'reviewer_id' => $this->latestDecision->reviewer_id,
                'decided_at' => $this->latestDecision->decided_at?->toIso8601String(),
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
