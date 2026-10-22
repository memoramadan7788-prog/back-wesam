<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExamAttempt
 */
class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'exam_id'        => $this->exam_id,
            'exam_title'     => $this->exam?->title,
            'time_limit_min' => $this->exam?->time_limit_min,
            'application_id' => $this->application_id,
            'attempt_no'     => $this->attempt_no,
            'status'         => $this->status,
            'started_at'     => $this->started_at?->toIso8601String(),
            'submitted_at'   => $this->submitted_at?->toIso8601String(),
            'grading_result' => $this->whenLoaded('gradingResults', fn () => $this->gradingResults->first() ? [
                'total_score' => (float) $this->gradingResults->first()->total_score,
                'status'      => $this->gradingResults->first()->status,
            ] : null),
        ];
    }
}

