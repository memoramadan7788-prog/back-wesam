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
            // company_id is auto-resolved from the authenticated user (Phase 0.1)
            // Legacy aliases are intentionally rejected; clients must use the canonical API contract.
            'job_category_id'  => ['prohibited'],
            'workplace_type'   => ['prohibited'],
            'min_salary'       => ['prohibited'],
            'max_salary'       => ['prohibited'],
            'city'             => ['prohibited'],
            'country_id'       => ['prohibited'],
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
            'private_apply_mode' => ['nullable', Rule::in(['all_employees', 'nominated_only'])],
            'skills'           => ['nullable', 'array'],
            'requirements'     => ['nullable', 'string', 'max:20000'],
            'benefits'         => ['nullable', 'array'],
            'benefits.*'       => ['string', 'max:255'],
            'requisition_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'hiring_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'hiring_model'     => ['nullable', Rule::in(['direct', 'agency'])],
            'allow_agency_submissions' => ['nullable', 'boolean'],
            'publish_to_search' => ['nullable', 'boolean'],
            'requisition_code' => ['nullable', 'string', 'max:100'],
            'status'           => ['nullable', Rule::in(['draft', 'published', 'closed', 'archived'])],
            'screening_questions' => ['nullable', 'array'],
            'screening_questions.*.question' => ['required_with:screening_questions', 'string', 'max:2000'],
            'screening_questions.*.type' => ['nullable', 'string', 'max:30'],
            'screening_questions.*.options' => ['nullable', 'array'],
            'screening_questions.*.is_required' => ['nullable', 'boolean'],
        ];
    }
}
