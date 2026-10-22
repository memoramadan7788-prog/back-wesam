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
            'questions'      => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}

