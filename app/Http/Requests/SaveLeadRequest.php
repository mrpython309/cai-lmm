<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Models\LeadOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class SaveLeadRequest extends FormRequest
{
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

        return [
            'lead_source_channel' => ['nullable', 'string', $choice('lead_source')],
            'assigned_employee_name' => ['nullable', 'string', 'max:255'],
            'lead_source' => ['required', 'string', 'max:120'],
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
            'recommended_model' => ['required', 'string', 'max:120'],
            'test_drive' => ['nullable', 'string', $choice('test_drive')],
            'finance' => ['nullable', 'string', $choice('finance')],
            'priority' => ['required', 'string', $choice('priority')],
            'follow_up_date' => $followUp,
            'remarks' => ['nullable', 'string', 'max:5000'],

            // Mahindra CRM Master References
            'organization_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'assigned_employee_id' => ['nullable', 'integer'],
            'vehicle_model_id' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'variant_name' => ['nullable', 'string', 'max:120'],
            'enquiry_type_id' => ['nullable', 'integer'],
            'enquiry_type' => ['nullable', 'string', 'max:120'],
            'lead_source_id' => ['nullable', 'integer'],
            'lead_status_id' => ['nullable', 'integer'],
            'lead_status' => ['nullable', 'string', 'max:120'],
            'status_response_id' => ['nullable', 'integer'],
            'status_response' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a 10-digit mobile number starting with 6, 7, 8, or 9.',
            'hotspot.in' => 'Select a hotspot for the chosen vehicle application.',
            'recommended_model.in' => 'Select a recommended model for the chosen vehicle application.',
            'follow_up_date.after_or_equal' => 'Choose today or a future follow-up date.',
        ];
    }

    public function leadAttributes(): array
    {
        $attributes = array_replace(array_fill_keys((new Lead)->getFillable(), null), $this->validated());
        $existing = $this->route('lead');
        if ($existing && ! $this->has('lead_source_channel')) {
            $attributes['lead_source_channel'] = $existing->lead_source_channel;
        }
        if (empty($attributes['assigned_employee_id'])) {
            $attributes['assigned_employee_name'] = null;
        } elseif ($existing && ! $this->has('assigned_employee_name') &&
            (int) $attributes['assigned_employee_id'] === $existing->assigned_employee_id) {
            $attributes['assigned_employee_name'] = $existing->assigned_employee_name;
        }

        return $attributes;
    }
}
