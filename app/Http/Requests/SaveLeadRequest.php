<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Models\LeadOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class SaveLeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('organization_id') && ($this->input('organization_id') === '' || $this->input('organization_id') === null)) {
            $this->merge(['organization_id' => null]);
        }
        if ($this->has('branch_id') && ($this->input('branch_id') === '' || $this->input('branch_id') === null)) {
            $this->merge(['branch_id' => null]);
        }
        if ($this->has('assigned_employee_id') && ($this->input('assigned_employee_id') === '' || $this->input('assigned_employee_id') === null)) {
            $this->merge(['assigned_employee_id' => null]);
        }
    }

    public function rules(): array
    {
        $options = LeadOption::query()->get(['field', 'scope', 'value', 'requires_company'])->groupBy('field');
        $choice = function (string $field, mixed $scope = '') use ($options): In {
            return Rule::in($options->get($field, collect())->whereStrict('scope', $scope)->pluck('value')->all());
        };

        $application = $this->input('application');
        $companyRequired = $options->get('business_type', collect())
            ->whereStrict('scope', '')
            ->firstWhere('value', '===', $this->input('business_type'))?->requires_company ?? false;

        $followUp = ['required', 'date_format:Y-m-d'];
        if ($this->input('follow_up_date') !== $this->route('lead')?->follow_up_date->toDateString()) {
            $followUp[] = 'after_or_equal:'.today()->toDateString();
        }

        $lead = $this->route('lead');
        $orgId = $this->input('organization_id');

        $orgRules = ['nullable', 'integer', 'required_with:branch_id'];
        if ($this->filled('organization_id')) {
            $isRetainedOrg = $lead && $lead->organization_id && (int) $orgId === (int) $lead->organization_id;
            if ($isRetainedOrg) {
                $orgRules[] = 'exists:organizations,id';
            } else {
                $orgRules[] = Rule::exists('organizations', 'id')->where('is_active', true);
            }
        }

        $branchRules = ['nullable', 'integer'];
        if ($this->filled('branch_id')) {
            $branchId = $this->input('branch_id');
            $isRetainedBranch = $lead && $lead->branch_id && (int) $branchId === (int) $lead->branch_id;

            $branchExists = Rule::exists('branches', 'id')
                ->where(fn ($query) => $query->where('organization_id', $orgId));

            if (! $isRetainedBranch) {
                $branchExists->where('is_active', true);
            }

            $branchRules[] = $branchExists;
        }

        $empId = $this->input('assigned_employee_id');
        $empRules = ['nullable', 'integer'];
        if ($this->filled('assigned_employee_id')) {
            $isRetainedEmp = $lead && $lead->assigned_employee_id && (int) $empId === (int) $lead->assigned_employee_id;
            $empExists = Rule::exists('employees', 'id')
                ->where(function ($query) use ($orgId) {
                    if ($orgId) {
                        $query->where('organization_id', $orgId);
                    }
                    if ($this->input('branch_id')) {
                        $query->where('branch_id', $this->input('branch_id'));
                    }
                });

            if (! $isRetainedEmp) {
                $empExists->where('is_active', true);
            }

            $empRules[] = $empExists;
        }

        return [
            'organization_id' => $orgRules,
            'branch_id' => $branchRules,
            'assigned_employee_id' => $empRules,
            'lead_source' => ['required', 'string', $choice('lead_source')],
            'business_type' => ['required', 'string', $choice('business_type')],
            'company_name' => [Rule::excludeIf(! $companyRequired), 'required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/\A[6-9][0-9]{9}\z/'],
            'email' => ['nullable', 'string', 'email', 'max:254'],
            'application' => ['required', 'string', $choice('application')],
            'vehicle_category' => ['required', 'string', $choice('vehicle_category')],
            'hotspot' => ['required', 'string', $choice('hotspot', $application)],
            'current_vehicle' => ['nullable', 'string', $choice('current_vehicle')],
            'exchange' => ['nullable', 'string', $choice('exchange')],
            'recommended_model' => ['required', 'string', $choice('recommended_model', $application)],
            'test_drive' => ['nullable', 'string', $choice('test_drive')],
            'finance' => ['nullable', 'string', $choice('finance')],
            'priority' => ['required', 'string', $choice('priority')],
            'follow_up_date' => $followUp,
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required_with' => 'Select an organization when choosing a branch.',
            'organization_id.exists' => 'The selected organization is invalid or inactive.',
            'branch_id.required_with' => 'An organization must be selected for the branch.',
            'branch_id.exists' => 'The selected branch is invalid, inactive, or does not belong to the selected organization.',
            'assigned_employee_id.exists' => 'The selected employee is invalid, inactive, or does not belong to the selected branch and organization.',
            'mobile.regex' => 'Enter a 10-digit mobile number starting with 6, 7, 8, or 9.',
            'hotspot.in' => 'Select a hotspot for the chosen vehicle application.',
            'recommended_model.in' => 'Select a recommended model for the chosen vehicle application.',
            'follow_up_date.after_or_equal' => 'Choose today or a future follow-up date.',
        ];
    }

    public function leadAttributes(): array
    {
        return array_replace(array_fill_keys((new Lead)->getFillable(), null), $this->validated());
    }
}
