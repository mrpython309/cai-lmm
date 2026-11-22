<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function rules(): array
    {
        $employee = $this->route('employee');
        $orgId = $this->input('organization_id');
        $branchId = $this->input('branch_id');

        $isRetainedOrg = $employee && (int) $orgId === (int) $employee->organization_id;
        $isRetainedBranch = $employee && (int) $branchId === (int) $employee->branch_id;

        $orgRule = $isRetainedOrg
            ? 'exists:organizations,id'
            : Rule::exists('organizations', 'id')->where('is_active', true);

        $branchRule = $isRetainedBranch
            ? Rule::exists('branches', 'id')->where(fn ($query) => $query->where('organization_id', $orgId))
            : Rule::exists('branches', 'id')->where(fn ($query) => $query->where('organization_id', $orgId)->where('is_active', true));

        return [
            'organization_id' => [
                'required',
                'integer',
                $orgRule,
            ],
            'branch_id' => [
                'required',
                'integer',
                $branchRule,
            ],
            'employee_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_id')->ignore($employee?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'email' => [
                'required',
                'email',
                'max:254',
                Rule::unique('employees', 'email')->ignore($employee?->id),
            ],
            'mobile_number' => [
                'required',
                'string',
                'max:20',
            ],
            'designation' => [
                'required',
                'string',
                'max:100',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.exists' => 'The selected organization is invalid or inactive.',
            'branch_id.exists' => 'The selected branch is invalid, inactive, or does not belong to the selected organization.',
            'employee_id.unique' => 'The employee ID has already been taken.',
            'email.unique' => 'The email address has already been taken.',
        ];
    }

    public function employeeAttributes(): array
    {
        return [
            'organization_id' => (int) $this->input('organization_id'),
            'branch_id' => (int) $this->input('branch_id'),
            'employee_id' => trim((string) $this->input('employee_id')),
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'mobile_number' => trim((string) $this->input('mobile_number')),
            'designation' => trim((string) $this->input('designation')),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
