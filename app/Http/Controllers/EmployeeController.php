<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::query()->with(['organization', 'branch']);

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->input('organization_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif ($request->boolean('active') || $request->has('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('all')) {
            return EmployeeResource::collection($query->orderBy('name')->get());
        }

        return EmployeeResource::collection($query->latest('id')->paginate(15));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->employeeAttributes());
        $employee->load(['organization', 'branch']);

        return (new EmployeeResource($employee))->response()
            ->setStatusCode(201)
            ->header('Location', route('api.employees.show', $employee));
    }

    public function show(Employee $employee): EmployeeResource
    {
        $employee->load(['organization', 'branch']);

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->update($request->employeeAttributes());
        $employee->load(['organization', 'branch']);

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee): Response
    {
        $employee->delete();

        return response()->noContent();
    }
}
