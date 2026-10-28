<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class AssignUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id'    => ['required', 'integer', 'exists:roles,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
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

            // User-category roles cannot have a company_id
            if (! $role->isCompanyRole() && ! empty($this->input('company_id'))) {
                $validator->errors()->add('company_id', 'Platform-scoped roles cannot be associated with a company.');
            }

            // Company-category roles require a company_id
            if ($role->isCompanyRole() && empty($this->input('company_id'))) {
                $validator->errors()->add('company_id', 'Company-scoped roles require a valid company_id.');
            }
        });
    }
}
