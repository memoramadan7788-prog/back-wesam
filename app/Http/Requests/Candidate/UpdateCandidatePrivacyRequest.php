<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidatePrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visibility' => ['sometimes', Rule::in(['public', 'employers_only', 'private'])],
            'allow_agency_recruiters' => ['sometimes', 'boolean'],
            'show_in_public_search' => ['sometimes', 'boolean'],
        ];
    }
}
