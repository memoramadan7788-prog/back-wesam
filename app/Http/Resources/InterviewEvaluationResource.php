<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewEvaluationResource extends JsonResource
{
    /**
     * Transform the resource into an array (ERD Page 4).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'interview_id' => $this->interview_id,
            'evaluator_id' => $this->evaluator_id,
            'numeric_score' => $this->numeric_score,
            'internal_notes' => $this->internal_notes,
            'candidate_feedback' => $this->candidate_feedback,
            'feedback_shared_at' => $this->feedback_shared_at?->toIso8601String(),
            'evaluator' => $this->whenLoaded('evaluator', function () {
                return [
                    'id' => $this->evaluator->id,
                    'name' => $this->evaluator->name,
                    'email' => $this->evaluator->email,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
