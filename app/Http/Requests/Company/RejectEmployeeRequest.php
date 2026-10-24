<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class RejectEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}

