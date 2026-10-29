<?php

namespace App\Http\Requests;

use App\Models\Job;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class JobIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            // Search
            'q'               => ['nullable', 'string', 'max:100'],
            'title'           => ['nullable', 'string', 'max:100'],
            'company_name'    => ['nullable', 'string', 'max:100'],

            // Filters
            'location'        => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', 'array', 'max:10'],
            'employment_type.*' => ['string', 'max:50', 'regex:/^[A-Za-z0-9_\- ]+$/'],
            'work_mode'       => ['nullable', 'array', 'max:3'],
            'work_mode.*'     => [Rule::in(['onsite', 'remote', 'hybrid'])],
            'company_id'      => ['nullable', 'integer', 'min:1'],
            'category_id'     => ['nullable', 'integer', 'min:1'],
            'skills'          => ['nullable', 'array', 'max:10'],
            'skills.*'        => ['integer', 'min:1'],
            'max_experience'  => ['nullable', 'integer', 'min:0', 'max:50'],
            'salary_min'      => ['nullable', 'numeric', 'min:0'],
            'salary_max'      => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'published_from'  => ['nullable', 'date'],
            'published_to'    => ['nullable', 'date', 'after_or_equal:published_from'],

            // Sorting (allowlist)
            'sort_by'         => ['nullable', Rule::in(array_keys(Job::SORTABLE))],
            'sort_dir'        => ['nullable', Rule::in(['asc', 'desc'])],

            // Pagination
            'page'            => ['nullable', 'integer', 'min:1'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['employment_type', 'work_mode'] as $field) {
            if ($this->filled($field) && ! is_array($this->input($field))) {
                $data[$field] = [$this->input($field)];
            }
        }

        if (! $this->filled('category_id') && $this->filled('discipline')) {
            $data['category_id'] = $this->input('discipline');
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    /**
     * Force JSON 422 validation response even if Accept header is missing.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        foreach (['employment_type', 'work_mode'] as $field) {
            $nestedKey = collect(array_keys($errors))->first(fn (string $key) => str_starts_with($key, $field . '.'));
            if ($nestedKey && ! isset($errors[$field])) {
                $errors[$field] = $errors[$nestedKey];
            }
        }

        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors'  => $errors,
        ], 422));
    }
}
