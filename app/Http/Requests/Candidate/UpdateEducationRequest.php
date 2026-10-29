<?php

namespace App\Http\Requests\Candidate;

class UpdateEducationRequest extends StoreEducationRequest
{
    public function rules(): array
    {
        return [
            'institution' => ['sometimes', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['sometimes', 'integer', 'min:1950', 'max:2050'],
            'place' => ['nullable', 'string', 'max:255'],
        ];
    }
}
