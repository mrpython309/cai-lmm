<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\LeadAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Lead::with(['organization', 'branch', 'assignedEmployee']);

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->input('organization_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('assigned_employee_id')) {
            $empFilter = $request->input('assigned_employee_id');
            if ($empFilter === 'unassigned') {
                $query->whereNull('assigned_employee_id');
            } else {
                $query->where('assigned_employee_id', $empFilter);
            }
        }

        return LeadResource::collection($query->latest('id')->paginate(15));
    }

    public function store(SaveLeadRequest $request): JsonResponse
    {
        $lead = DB::transaction(function () use ($request) {
            $lead = Lead::create($request->leadAttributes());

            if ($lead->assigned_employee_id) {
                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'previous_employee_id' => null,
                    'new_employee_id' => $lead->assigned_employee_id,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'remarks' => 'Assigned during initial lead registration.',
                ]);
            }

            return $lead;
        });

        $lead->load(['organization', 'branch', 'assignedEmployee']);

        return (new LeadResource($lead))->response()
            ->setStatusCode(201)
            ->header('Location', route('api.leads.show', $lead));
    }

    public function show(Lead $lead): LeadResource
    {
        $lead->load(['organization', 'branch', 'assignedEmployee', 'assignments.previousEmployee', 'assignments.newEmployee', 'assignments.assigner']);

        return new LeadResource($lead);
    }

    public function update(SaveLeadRequest $request, Lead $lead): LeadResource
    {
        $previousEmployeeId = $lead->assigned_employee_id;
        $newEmployeeId = $request->filled('assigned_employee_id') ? (int) $request->input('assigned_employee_id') : null;

        DB::transaction(function () use ($request, $lead, $previousEmployeeId, $newEmployeeId) {
            $lead->update($request->leadAttributes());

            if ($newEmployeeId && (int) $newEmployeeId !== (int) $previousEmployeeId) {
                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'previous_employee_id' => $previousEmployeeId,
                    'new_employee_id' => $newEmployeeId,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'remarks' => 'Reassigned during lead update.',
                ]);
            }
        });

        $lead->load(['organization', 'branch', 'assignedEmployee', 'assignments.previousEmployee', 'assignments.newEmployee', 'assignments.assigner']);

        return new LeadResource($lead);
    }

    public function destroy(Lead $lead): Response
    {
        $lead->delete();

        return response()->noContent();
    }
}
