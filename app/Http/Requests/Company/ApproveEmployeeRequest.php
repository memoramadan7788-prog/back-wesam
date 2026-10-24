<?php

namespace App\Http\Requests\Company;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class ApproveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roleId = $this->input('role_id');
            if (! $roleId) {
                return;
            }

            $role = Role::find($roleId);
            if (! $role) {
                return;
            }

            // Must be a company-scoped role (hr, interviewer, employee)
            if ($role->scope !== 'company') {
                $validator->errors()->add('role_id', 'Assigned role must have company scope (e.g., hr, interviewer, employee).');
            }
        });
    }
}

