<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cv_id' => ['nullable', 'integer', 'exists:candidate_cvs,id'],
            'answers' => ['sometimes', 'array', 'max:100'],
            'answers.*.question_id' => ['required_with:answers', 'integer', 'exists:job_screening_questions,id'],
            'answers.*.answer' => ['nullable'],
            'answers.*.value' => ['nullable'],
            'answers.*.selected_option_ids' => ['nullable', 'array'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'portfolio_url' => ['nullable', 'url', 'max:2048'],
            'cover_note' => ['nullable', 'string', 'max:3000'],
            'keep_in_talent_pool' => ['sometimes', 'boolean'],
            'consent' => ['sometimes', 'boolean'],
        ];
    }
}
