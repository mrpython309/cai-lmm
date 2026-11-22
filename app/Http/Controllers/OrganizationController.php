<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Organization::query()->withCount('branches');

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif ($request->boolean('active') || $request->has('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->boolean('all')) {
            return OrganizationResource::collection($query->orderBy('organization_name')->get());
        }

        return OrganizationResource::collection($query->latest('id')->paginate(15));
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = Organization::create($request->organizationAttributes());

        return (new OrganizationResource($organization))->response()
            ->setStatusCode(201)
            ->header('Location', route('api.organizations.show', $organization));
    }

    public function show(Organization $organization): OrganizationResource
    {
        $organization->loadCount('branches');

        return new OrganizationResource($organization);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): OrganizationResource
    {
        $organization->update($request->organizationAttributes());

        return new OrganizationResource($organization);
    }

    public function destroy(Organization $organization): JsonResponse|Response
    {
        if ($organization->branches()->exists()) {
            return response()->json([
                'message' => 'Cannot delete organization because it has associated branches. Delete or reassign branches first.',
            ], 422);
        }

        $organization->delete();

        return response()->noContent();
    }
}
