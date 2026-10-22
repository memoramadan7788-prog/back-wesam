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
        ];
    }
}

