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
            'company_id'     => ['required', 'integer', 'exists:companies,id'],
            'title'          => ['required', 'string', 'max:255'],
            'time_limit_min' => ['required', 'integer', 'min:5', 'max:300'],
            'passing_score'  => ['required', 'numeric', 'min:0', 'max:100'],
            'attempt_limit'  => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}

