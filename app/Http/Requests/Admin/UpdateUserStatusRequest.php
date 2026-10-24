<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_status_id' => ['required', 'integer', 'exists:account_statuses,id'],
            'reason'            => ['nullable', 'string', 'max:500'],
        ];
    }
}

