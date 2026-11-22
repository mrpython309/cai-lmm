<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'branch_code' => $this->branch_code,
            'branch_name' => $this->branch_name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
