<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function rules(): array
    {
        $branch = $this->route('branch');
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $orgId = $this->input('organization_id', $branch instanceof Branch ? $branch->organization_id : null);

        return [
            'organization_id' => ['sometimes', 'required', 'integer', 'exists:organizations,id'],
            'branch_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches', 'branch_code')
                    ->where(fn ($query) => $query->where('organization_id', $orgId))
                    ->ignore($branchId),
            ],
            'branch_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'contact_number' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:254'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_code.unique' => 'The branch code has already been taken for this organization.',
        ];
    }

    public function branchAttributes(): array
    {
        $branch = $this->route('branch');
        $orgId = $this->input('organization_id', $branch instanceof Branch ? $branch->organization_id : null);

        return [
            'organization_id' => (int) $orgId,
            'branch_code' => trim((string) $this->input('branch_code')),
            'branch_name' => trim((string) $this->input('branch_name')),
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'city' => $this->filled('city') ? trim((string) $this->input('city')) : null,
            'state' => $this->filled('state') ? trim((string) $this->input('state')) : null,
            'pincode' => $this->filled('pincode') ? trim((string) $this->input('pincode')) : null,
            'contact_person' => $this->filled('contact_person') ? trim((string) $this->input('contact_person')) : null,
            'contact_number' => $this->filled('contact_number') ? trim((string) $this->input('contact_number')) : null,
            'email' => $this->filled('email') ? Str::lower(trim((string) $this->input('email'))) : null,
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
