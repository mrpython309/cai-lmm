<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->resource->only($this->resource->getFillable()),
            'follow_up_date' => $this->follow_up_date->toDateString(),
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'assigned_employee' => new EmployeeResource($this->whenLoaded('assignedEmployee')),
            'assignment_history' => LeadAssignmentResource::collection($this->whenLoaded('assignments')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
