<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'           => ['required', 'integer', 'exists:companies,id'],
            'requested_job_title'  => ['required', 'string', 'max:100'],
            'requested_department' => ['nullable', 'string', 'max:100'],
        ];
    }
}

