<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_code' => $this->organization_code,
            'organization_name' => $this->organization_name,
            'organization_type' => $this->organization_type,
            'is_active' => (bool) $this->is_active,
            'branches_count' => $this->whenCounted('branches'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
