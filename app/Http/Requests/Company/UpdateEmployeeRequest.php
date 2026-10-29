<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_job_title'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'requested_department' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->has('requested_job_title') && !$this->has('requested_department')) {
                $validator->errors()->add('update_data', 'At least one field (requested_job_title or requested_department) must be provided.');
            }
        });
    }
}