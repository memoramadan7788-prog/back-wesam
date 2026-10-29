<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desired_titles' => ['sometimes', 'array'],
            'desired_titles.*' => ['string', 'max:100'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'min_salary' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'work_model' => ['sometimes', 'nullable', 'in:onsite,remote,hybrid'],
            'open_to_relocation' => ['sometimes', 'boolean'],
            'include_agency' => ['sometimes', 'boolean'],
        ];
    }
}
