<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'       => ['required', 'integer', 'exists:companies,id'],
            'category_id'      => ['nullable', 'integer', 'exists:job_categories,id'],
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['required', 'string', 'min:20'],
            'location'         => ['nullable', 'string', 'max:255'],
            'work_mode'        => ['required', Rule::in(['onsite', 'remote', 'hybrid'])],
            'employment_type'  => ['required', 'string', 'max:50'],
            'salary_min'       => ['nullable', 'numeric', 'min:0'],
            'salary_max'       => ['nullable', 'numeric', 'gte:salary_min'],
            'currency'         => ['nullable', 'string', 'size:3'],
            'salary_period'    => ['nullable', Rule::in(['hour', 'day', 'week', 'month', 'year'])],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'department'       => ['nullable', 'string', 'max:100'],
            'openings_count'   => ['nullable', 'integer', 'min:1'],
            'deadline'         => ['nullable', 'date', 'after:today'],
            'visibility'       => ['nullable', Rule::in(['public', 'private'])],
            'skills'           => ['nullable', 'array'],
        ];
    }
}

