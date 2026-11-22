<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class AssignLeadRequest extends FormRequest
{
    public function rules(): array
    {
        $lead = $this->route('lead');

        return [
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
                function ($attribute, $value, $fail) use ($lead) {
                    $employee = Employee::find($value);
                    if (! $employee) {
                        return;
                    }

                    if (! $employee->is_active) {
                        $fail('The selected employee is inactive and cannot be assigned.');
                        return;
                    }

                    if ($lead && $lead->organization_id && (int) $employee->organization_id !== (int) $lead->organization_id) {
                        $fail('The selected employee does not belong to the lead\'s organization.');
                        return;
                    }

                    if ($lead && $lead->branch_id && (int) $employee->branch_id !== (int) $lead->branch_id) {
                        $fail('The selected employee does not belong to the lead\'s branch.');
                        return;
                    }
                },
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee to assign.',
            'employee_id.exists' => 'The selected employee was not found.',
        ];
    }
}
