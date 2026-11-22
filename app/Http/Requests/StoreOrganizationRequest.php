<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'organization_code' => ['required', 'string', 'max:50', 'unique:organizations,organization_code'],
            'organization_name' => ['required', 'string', 'max:150'],
            'organization_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function organizationAttributes(): array
    {
        return [
            'organization_code' => trim((string) $this->input('organization_code')),
            'organization_name' => trim((string) $this->input('organization_name')),
            'organization_type' => $this->filled('organization_type') ? trim((string) $this->input('organization_type')) : null,
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
