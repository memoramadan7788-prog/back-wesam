<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Question
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hideAnswers = $request->attributes->get('is_candidate_exam', false);

        return [
            'id'            => $this->id,
            'question_type' => $this->question_type,
            'content'       => $this->content,
            'points'        => $this->points,
            'order_no'      => $this->order_no,
            'options'       => $this->whenLoaded('options', fn () => $this->options->map(fn ($opt) => array_filter([
                'id'          => $opt->id,
                'option_text' => $opt->option_text,
                'is_correct'  => $hideAnswers ? null : (bool) $opt->is_correct,
            ], fn ($v) => $v !== null))),
        ];
    }
}

