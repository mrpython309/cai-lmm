<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignLeadRequest;
use App\Http\Resources\LeadAssignmentResource;
use App\Http\Resources\LeadResource;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class LeadAssignmentController extends Controller
{
    public function index(Lead $lead): AnonymousResourceCollection
    {
        $assignments = $lead->assignments()
            ->with(['previousEmployee.organization', 'previousEmployee.branch', 'newEmployee.organization', 'newEmployee.branch', 'assigner'])
            ->get();

        return LeadAssignmentResource::collection($assignments);
    }

    public function assign(AssignLeadRequest $request, Lead $lead): JsonResponse
    {
        $newEmployeeId = (int) $request->input('employee_id');
        $previousEmployeeId = $lead->assigned_employee_id;
        $employee = Employee::with(['organization', 'branch'])->findOrFail($newEmployeeId);

        DB::transaction(function () use ($lead, $employee, $previousEmployeeId, $request) {
            // Align organization and branch if lead is unassigned to branch/organization
            if (! $lead->organization_id && ! $lead->branch_id) {
                $lead->organization_id = $employee->organization_id;
                $lead->branch_id = $employee->branch_id;
            }

            $lead->assigned_employee_id = $employee->id;
            $lead->save();

            LeadAssignment::create([
                'lead_id' => $lead->id,
                'previous_employee_id' => $previousEmployeeId,
                'new_employee_id' => $employee->id,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
                'remarks' => $request->filled('remarks') ? trim((string) $request->input('remarks')) : null,
            ]);
        });

        $lead->load(['organization', 'branch', 'assignedEmployee', 'assignments.previousEmployee', 'assignments.newEmployee', 'assignments.assigner']);

        return (new LeadResource($lead))->response()
            ->setStatusCode(200);
    }
}
