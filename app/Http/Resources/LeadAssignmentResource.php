<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'previous_employee_id' => $this->previous_employee_id,
            'previous_employee' => new EmployeeResource($this->whenLoaded('previousEmployee')),
            'new_employee_id' => $this->new_employee_id,
            'new_employee' => new EmployeeResource($this->whenLoaded('newEmployee')),
            'assigned_by_id' => $this->assigned_by,
            'assigned_by' => $this->assigner?->name ?? 'System User',
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'assigned_at_formatted' => $this->assigned_at?->format('d M Y, h:i A'),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
