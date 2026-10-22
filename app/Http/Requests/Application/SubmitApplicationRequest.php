<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cv_id'      => ['nullable', 'integer', 'exists:candidate_cvs,id'],
            'cover_note' => ['nullable', 'string', 'max:3000'],
        ];
    }
}

