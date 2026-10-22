<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

class SubmitExamAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers'                        => ['required', 'array'],
            'answers.*.question_id'         => ['required', 'integer', 'exists:questions,id'],
            'answers.*.selected_option_id'  => ['nullable', 'integer', 'exists:question_options,id'],
            'answers.*.answer_text'         => ['nullable', 'string'],
            'answers.*.code'                => ['nullable', 'string'],
        ];
    }
}

