<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'  => ['required', Rule::in(['screening', 'shortlisted', 'interviewing', 'offered', 'hired', 'rejected'])],
            'comment' => ['nullable', 'string', 'max:1000'],
            'reason'  => ['nullable', 'string', 'max:1000'],
        ];
    }
}

