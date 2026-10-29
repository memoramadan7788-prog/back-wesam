<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'steps' => ['required', 'array'],
            'steps.basics' => ['sometimes', 'array'],
            'steps.basics.completed' => ['sometimes', 'boolean'],
            'steps.resume_import' => ['sometimes', 'array'],
            'steps.resume_import.completed' => ['sometimes', 'boolean'],
            'steps.skills_preferences' => ['sometimes', 'array'],
            'steps.skills_preferences.completed' => ['sometimes', 'boolean'],
        ];
    }
}
