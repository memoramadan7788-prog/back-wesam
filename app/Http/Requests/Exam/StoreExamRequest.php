<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // company_id is auto-resolved from the authenticated user (Phase 0.1)
            'title'          => ['required', 'string', 'max:255'],
            'time_limit_min' => ['required', 'integer', 'min:5', 'max:300'],
            'passing_score'  => ['required', 'numeric', 'min:0', 'max:100'],
            'attempt_limit'  => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
