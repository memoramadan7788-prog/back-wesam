<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class StoreEducationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'institution'     => ['required', 'string', 'max:255'],
            'degree'          => ['nullable', 'string', 'max:255'],
            'field_of_study'  => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['required', 'integer', 'min:1950', 'max:2050'],
            'place'           => ['nullable', 'string', 'max:255'],
        ];
    }
}

