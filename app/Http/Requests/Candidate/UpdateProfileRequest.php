<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'headline'           => ['nullable', 'string', 'max:255'],
            'bio'                => ['nullable', 'string', 'max:2000'],
            'phone'              => ['nullable', 'string', 'max:25'],
            'location'           => ['nullable', 'string', 'max:255'],
            'years_experience'   => ['nullable', 'integer', 'min:0', 'max:50'],
            'has_no_experience'  => ['nullable', 'boolean'],
        ];
    }
}

