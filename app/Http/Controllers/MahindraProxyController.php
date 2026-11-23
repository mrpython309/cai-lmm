<?php

namespace App\Http\Controllers;

use App\Services\MahindraApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MahindraProxyController extends Controller
{
    public function __construct(
        protected MahindraApiClient $apiClient
    ) {}

    /**
     * List active companies (organizations).
     */
    public function companies(): JsonResponse
    {
        $result = $this->apiClient->getCompanies();

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List active branches for a company.
     */
    public function branches(string|int $id): JsonResponse
    {
        $result = $this->apiClient->getBranches($id);

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List active departments.
     */
    public function departments(): JsonResponse
    {
        $result = $this->apiClient->getDepartments();

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List active designations.
     */
    public function designations(): JsonResponse
    {
        $result = $this->apiClient->getDesignations();

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List active employees with search and filters.
     */
    public function employees(Request $request): JsonResponse
    {
        $params = $request->only(['company_id', 'dealer_location_id', 'designation_id', 'search', 'page', 'per_page']);
        $result = $this->apiClient->getEmployees($params);

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List active vehicle models with optional company/branch filters.
     */
    public function vehicleModels(Request $request): JsonResponse
    {
        $params = $request->only(['company_id', 'dealer_location_id', 'segment_id']);
        $result = $this->apiClient->getVehicleModels($params);

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List active variants for a vehicle model.
     */
    public function variants(string|int $id): JsonResponse
    {
        $result = $this->apiClient->getVariants($id);

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List enquiry types.
     */
    public function enquiryTypes(): JsonResponse
    {
        $result = $this->apiClient->getEnquiryTypes();

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List lead sources for an enquiry type.
     */
    public function sources(string|int $id): JsonResponse
    {
        $result = $this->apiClient->getEnquirySources($id);

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List lead statuses.
     */
    public function leadStatuses(): JsonResponse
    {
        $result = $this->apiClient->getLeadStatuses();

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List status responses for a lead status.
     */
    public function responses(string|int $id): JsonResponse
    {
        $result = $this->apiClient->getStatusResponses($id);

        return response()->json($result['data'], $result['status']);
    }
}
