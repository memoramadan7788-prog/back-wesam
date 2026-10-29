<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Exam
 */
class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'time_limit_min' => $this->time_limit_min,
            'attempt_limit'  => $this->attempt_limit,
            'passing_score'  => (float) $this->passing_score,
            'status'         => $this->status,
            'questions_count' => $this->when(isset($this->questions_count), (int) $this->questions_count),
            'candidate_attempts_count' => $this->when(isset($this->candidate_attempts_count), (int) $this->candidate_attempts_count),
            'questions'      => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}

