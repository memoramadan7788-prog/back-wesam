<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class AtsDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accept,override'],
           
            'reason' => ['required_if:decision,override', 'nullable', 'string', 'max:1000'],
        ];
    }
}
