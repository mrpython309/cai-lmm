<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MahindraApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MahindraApiClientTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $baseUrl = 'http://mahindra-crm.test/api/v1';
    protected string $apiKey = 'test-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_requests_to_proxy_endpoints_are_rejected(): void
    {
        $endpoints = [
            '/api/mahindra/companies',
            '/api/mahindra/companies/1/branches',
            '/api/mahindra/departments',
            '/api/mahindra/designations',
            '/api/mahindra/employees',
            '/api/mahindra/vehicle-models',
            '/api/mahindra/vehicle-models/1/variants',
            '/api/mahindra/enquiry-types',
            '/api/mahindra/enquiry-types/1/sources',
            '/api/mahindra/lead-statuses',
            '/api/mahindra/lead-statuses/1/responses',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }

    public function test_authenticated_user_can_fetch_companies(): void
    {
        Http::fake([
            $this->baseUrl . '/companies' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Automotive Manufacturers', 'code' => 'AM01', 'status' => 'active'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/mahindra/companies');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Automotive Manufacturers'],
                ],
            ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === $this->baseUrl . '/companies'
                && $request->header('X-API-KEY')[0] === $this->apiKey;
        });
    }

    public function test_authenticated_user_can_fetch_branches_for_valid_company(): void
    {
        Http::fake([
            $this->baseUrl . '/companies/1/branches' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 10, 'company_id' => 1, 'name' => 'Hyderabad Central', 'status' => 'active'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/mahindra/companies/1/branches');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    ['id' => 10, 'name' => 'Hyderabad Central'],
                ],
            ]);
    }

    public function test_branches_returns_404_when_company_not_found_or_inactive(): void
    {
        Http::fake([
            $this->baseUrl . '/companies/999/branches' => Http::response([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ], 404),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/mahindra/companies/999/branches');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ]);
    }

    public function test_authenticated_user_can_fetch_departments_and_designations(): void
    {
        Http::fake([
            $this->baseUrl . '/departments' => Http::response([
                'success' => true,
                'data' => [['id' => 1, 'department_name' => 'Sales', 'status' => 'active']],
            ], 200),
            $this->baseUrl . '/designations' => Http::response([
                'success' => true,
                'data' => [['id' => 2, 'designation_name' => 'Sales Executive', 'status' => 'active']],
            ], 200),
        ]);

        $this->actingAs($this->user)->getJson('/api/mahindra/departments')
            ->assertOk()
            ->assertJsonPath('data.0.department_name', 'Sales');

        $this->actingAs($this->user)->getJson('/api/mahindra/designations')
            ->assertOk()
            ->assertJsonPath('data.0.designation_name', 'Sales Executive');
    }

    public function test_authenticated_user_can_fetch_employees_with_query_parameters(): void
    {
        Http::fake([
            $this->baseUrl . '/employees*' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 5, 'emp_code' => 'EMP005', 'employee_name' => 'Ravi Kumar'],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 1,
                    'last_page' => 1,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/mahindra/employees?company_id=1&search=Ravi&per_page=15');

        $response->assertOk()
            ->assertJsonPath('data.0.employee_name', 'Ravi Kumar')
            ->assertJsonPath('pagination.total', 1);

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), $this->baseUrl . '/employees')
                && $request['company_id'] == '1'
                && $request['search'] == 'Ravi'
                && $request['per_page'] == '15';
        });
    }

    public function test_authenticated_user_can_fetch_vehicle_models_and_variants(): void
    {
        Http::fake([
            $this->baseUrl . '/vehicle-models/3/variants' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 31, 'name' => 'Z8L Diesel AT', 'status' => 'active'],
                ],
            ], 200),
            $this->baseUrl . '/vehicle-models*' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 3, 'name' => 'Scorpio-N', 'status' => 'active'],
                ],
            ], 200),
        ]);

        $this->actingAs($this->user)->getJson('/api/mahindra/vehicle-models?company_id=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Scorpio-N');

        $this->actingAs($this->user)->getJson('/api/mahindra/vehicle-models/3/variants')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Z8L Diesel AT');
    }

    public function test_variants_returns_404_when_model_not_found(): void
    {
        Http::fake([
            $this->baseUrl . '/vehicle-models/888/variants' => Http::response([
                'success' => false,
                'message' => 'Vehicle model not found or inactive.',
            ], 404),
        ]);

        $this->actingAs($this->user)->getJson('/api/mahindra/vehicle-models/888/variants')
            ->assertNotFound()
            ->assertJsonPath('message', 'Vehicle model not found or inactive.');
    }

    public function test_authenticated_user_can_fetch_enquiry_types_and_sources(): void
    {
        Http::fake([
            $this->baseUrl . '/enquiry-types' => Http::response([
                'success' => true,
                'data' => [['id' => 1, 'name' => 'Walk In']],
            ], 200),
            $this->baseUrl . '/enquiry-types/1/sources' => Http::response([
                'success' => true,
                'data' => [['id' => 101, 'campaign' => 'Showroom Visit']],
            ], 200),
        ]);

        $this->actingAs($this->user)->getJson('/api/mahindra/enquiry-types')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Walk In');

        $this->actingAs($this->user)->getJson('/api/mahindra/enquiry-types/1/sources')
            ->assertOk()
            ->assertJsonPath('data.0.campaign', 'Showroom Visit');
    }

    public function test_authenticated_user_can_fetch_lead_statuses_and_responses(): void
    {
        Http::fake([
            $this->baseUrl . '/lead-statuses' => Http::response([
                'success' => true,
                'data' => [['id' => 1, 'name' => 'Hot']],
            ], 200),
            $this->baseUrl . '/lead-statuses/1/responses' => Http::response([
                'success' => true,
                'data' => [['id' => 201, 'response' => 'Test Drive Requested']],
            ], 200),
        ]);

        $this->actingAs($this->user)->getJson('/api/mahindra/lead-statuses')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Hot');

        $this->actingAs($this->user)->getJson('/api/mahindra/lead-statuses/1/responses')
            ->assertOk()
            ->assertJsonPath('data.0.response', 'Test Drive Requested');
    }

    public function test_upstream_unauthorized_is_handled_without_exposing_secrets(): void
    {
        Http::fake([
            $this->baseUrl . '/companies' => Http::response([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing API key.',
            ], 401),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/mahindra/companies');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing API key.',
            ]);

        $this->assertStringNotContainsString($this->apiKey, $response->getContent());
    }

    public function test_upstream_timeout_or_connection_failure_returns_504(): void
    {
        Http::fake([
            $this->baseUrl . '/companies' => function () {
                throw new ConnectionException('Connection timed out after 5000 milliseconds');
            },
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/mahindra/companies');

        $response->assertStatus(504)
            ->assertJson([
                'success' => false,
                'message' => 'Mahindra CRM service timed out or is unreachable.',
            ]);

        $this->assertStringNotContainsString($this->apiKey, $response->getContent());
    }

    public function test_unconfigured_api_key_returns_500_error(): void
    {
        config(['services.mahindra.api_key' => null]);

        $client = new MahindraApiClient();
        $result = $client->getCompanies();

        $this->assertSame(500, $result['status']);
        $this->assertFalse($result['data']['success']);
        $this->assertSame('Mahindra API client is not configured.', $result['data']['message']);
    }

    public function test_sensitive_employee_credentials_are_never_exposed(): void
    {
        Http::fake([
            $this->baseUrl . '/employees*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'id' => 1,
                        'emp_code' => 'EMP001',
                        'employee_name' => 'Safe User',
                        'email' => 'safe@example.com',
                        'mobile_number' => '9876543210',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/mahindra/employees');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString($this->apiKey, $content);
    }
}
