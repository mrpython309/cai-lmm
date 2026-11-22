<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Branch::query()->with('organization');

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->input('organization_id'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif ($request->boolean('active') || $request->has('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->boolean('all')) {
            return BranchResource::collection($query->orderBy('branch_name')->get());
        }

        return BranchResource::collection($query->latest('id')->paginate(15));
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->branchAttributes());
        $branch->load('organization');

        return (new BranchResource($branch))->response()
            ->setStatusCode(201)
            ->header('Location', route('api.branches.show', $branch));
    }

    public function show(Branch $branch): BranchResource
    {
        $branch->load('organization');

        return new BranchResource($branch);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch->update($request->branchAttributes());
        $branch->load('organization');

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch): Response
    {
        $branch->delete();

        return response()->noContent();
    }
}
