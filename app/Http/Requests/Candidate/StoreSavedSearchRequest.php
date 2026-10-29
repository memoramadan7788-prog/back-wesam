<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'filters' => ['required', 'array'],
            'weekly_alerts' => ['sometimes', 'boolean'],
            'is_paused' => ['sometimes', 'boolean'],
        ];
    }
}
