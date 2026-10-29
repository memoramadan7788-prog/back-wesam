<?php

namespace App\Http\Requests\Offer;

use Illuminate\Foundation\Http\FormRequest;

class CreateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'salary_amount'  => ['required', 'numeric', 'min:0'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'salary_period'  => ['nullable', 'string', 'max:20'],
            'start_date'     => ['required', 'date', 'after:today'],
            'expires_at'     => ['nullable', 'date', 'after:now'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'equity' => ['nullable', 'numeric', 'min:0'],
            'additional_terms' => ['nullable', 'array'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'work_model' => ['nullable', 'string', 'max:50'],
            'offer_letter_template_id' => ['nullable', 'integer', 'exists:offer_letter_templates,id'],
            'candidate_message' => ['nullable', 'string', 'max:5000'],
            'require_esignature' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,sent'],
        ];
    }
}

