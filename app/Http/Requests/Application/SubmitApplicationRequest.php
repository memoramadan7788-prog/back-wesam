<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cv_id'      => ['nullable', 'integer', 'exists:candidate_cvs,id'],
            'cover_note' => ['nullable', 'string', 'max:3000'],
            'answers' => ['sometimes', 'array', 'max:100'],
            'answers.*.question_id' => ['required_with:answers', 'integer', 'exists:job_screening_questions,id'],
            'answers.*.answer' => ['nullable'],
            'answers.*.value' => ['nullable'],
            'answers.*.selected_option_ids' => ['nullable', 'array'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'portfolio_url' => ['nullable', 'url', 'max:2048'],
            'keep_in_talent_pool' => ['sometimes', 'boolean'],
            'consent'    => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.required' => 'You must give consent to process your application data.',
            'consent.accepted' => 'You must give consent to process your application data.',
        ];
    }
}
