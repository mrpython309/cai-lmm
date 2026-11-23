<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MahindraApiClient
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.mahindra.base_url', 'http://127.0.0.1:8001/api/v1');
        $this->apiKey = config('services.mahindra.api_key');
        $this->timeout = (int) config('services.mahindra.timeout', 5);
    }

    /**
     * Get a configured HTTP client instance.
     */
    protected function client(): PendingRequest
    {
        $client = Http::timeout($this->timeout)
            ->acceptJson();

        if ($this->apiKey) {
            $client->withHeaders([
                'X-API-KEY' => $this->apiKey,
            ]);
        }

        return $client;
    }

    /**
     * Execute a GET request to the Mahindra API.
     */
    public function get(string $endpoint, array $query = []): array
    {
        if (empty($this->apiKey)) {
            Log::error('Mahindra API error: API key is not configured.');

            return [
                'status' => 500,
                'data' => [
                    'success' => false,
                    'message' => 'Mahindra API client is not configured.',
                ],
            ];
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = $this->client()->get($url, $query);

            return [
                'status' => $response->status(),
                'data' => $response->json() ?? [
                    'success' => $response->successful(),
                    'message' => $response->body(),
                ],
            ];
        } catch (ConnectionException $e) {
            Log::error('Mahindra API connection error: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
            ]);

            return [
                'status' => 504,
                'data' => [
                    'success' => false,
                    'message' => 'Mahindra CRM service timed out or is unreachable.',
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Mahindra API unexpected error: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
            ]);

            return [
                'status' => 500,
                'data' => [
                    'success' => false,
                    'message' => 'An error occurred while communicating with Mahindra CRM.',
                ],
            ];
        }
    }

    /**
     * List all active companies.
     */
    public function getCompanies(): array
    {
        return $this->get('companies');
    }

    /**
     * List active branches for a company.
     */
    public function getBranches(string|int $companyId): array
    {
        return $this->get("companies/{$companyId}/branches");
    }

    /**
     * List all active departments.
     */
    public function getDepartments(): array
    {
        return $this->get('departments');
    }

    /**
     * List all active designations.
     */
    public function getDesignations(): array
    {
        return $this->get('designations');
    }

    /**
     * List active employees with optional filters/search.
     */
    public function getEmployees(array $params = []): array
    {
        return $this->get('employees', $params);
    }

    /**
     * List active vehicle models with optional filters.
     */
    public function getVehicleModels(array $params = []): array
    {
        return $this->get('vehicle-models', $params);
    }

    /**
     * List active variants for a vehicle model.
     */
    public function getVariants(string|int $modelId): array
    {
        return $this->get("vehicle-models/{$modelId}/variants");
    }

    /**
     * List all enquiry types.
     */
    public function getEnquiryTypes(): array
    {
        return $this->get('enquiry-types');
    }

    /**
     * List lead sources for an enquiry type.
     */
    public function getEnquirySources(string|int $typeId): array
    {
        return $this->get("enquiry-types/{$typeId}/sources");
    }

    /**
     * List all lead statuses.
     */
    public function getLeadStatuses(): array
    {
        return $this->get('lead-statuses');
    }

    /**
     * List status responses for a lead status.
     */
    public function getStatusResponses(string|int $statusId): array
    {
        return $this->get("lead-statuses/{$statusId}/responses");
    }
}
