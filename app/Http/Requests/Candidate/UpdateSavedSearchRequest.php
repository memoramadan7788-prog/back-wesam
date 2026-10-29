<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'filters' => ['sometimes', 'array'],
            'weekly_alerts' => ['sometimes', 'boolean'],
            'is_paused' => ['sometimes', 'boolean'],
        ];
    }
}
