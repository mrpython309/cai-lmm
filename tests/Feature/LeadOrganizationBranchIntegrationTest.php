<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LeadOrganizationBranchIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        $driver = $connection->getDriverName();

        if (($driver === 'sqlite' && $database === ':memory:') || ($driver === 'mysql' && str_ends_with($database, '_test'))) {
            return;
        }

        throw new RuntimeException('Use in-memory SQLite or a dedicated MySQL database ending in _test.');
    }

    protected function baseLeadData(array $overrides = []): array
    {
        return array_merge([
            'lead_source' => 'Digital',
            'business_type' => 'Individual',
            'location' => 'Velachery, Chennai',
            'customer_name' => 'Karthik Raja',
            'mobile' => '9840123456',
            'email' => 'karthik@example.com',
            'application' => 'Passenger',
            'vehicle_category' => '3 Wheeler',
            'hotspot' => 'Railway stations and bus stands',
            'current_vehicle' => 'None',
            'exchange' => 'No',
            'recommended_model' => 'Treo',
            'test_drive' => 'Yes',
            'finance' => 'Yes',
            'priority' => 'HOT',
            'follow_up_date' => today()->addDays(5)->toDateString(),
            'remarks' => 'Integration test lead',
        ], $overrides);
    }

    public function test_1_create_lead_with_valid_organization_and_branch(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $org = Organization::create([
            'organization_code' => 'ORG_VALID',
            'organization_name' => 'Mahindra Chennai Dealers',
            'is_active' => true,
        ]);

        $branch = $org->branches()->create([
            'branch_code' => 'BR_VALID',
            'branch_name' => 'Chennai Main',
            'is_active' => true,
        ]);

        $payload = $this->baseLeadData([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($staff)->postJson('/api/leads', $payload)
            ->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.organization_id', $org->id)
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.organization.organization_code', 'ORG_VALID')
            ->assertJsonPath('data.branch.branch_code', 'BR_VALID');

        $leadId = $response->json('data.id');
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
        ]);

        $lead = Lead::with(['organization', 'branch'])->find($leadId);
        $this->assertSame('Mahindra Chennai Dealers', $lead->organization->organization_name);
        $this->assertSame('Chennai Main', $lead->branch->branch_name);
    }

    public function test_2_reject_branch_belonging_to_another_organization(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $org1 = Organization::create(['organization_code' => 'ORG_A', 'organization_name' => 'Org A', 'is_active' => true]);
        $org2 = Organization::create(['organization_code' => 'ORG_B', 'organization_name' => 'Org B', 'is_active' => true]);

        $branchUnderOrg2 = $org2->branches()->create(['branch_code' => 'BR_B', 'branch_name' => 'Branch B', 'is_active' => true]);

        // Attempt to assign branchUnderOrg2 with org1
        $payload = $this->baseLeadData([
            'organization_id' => $org1->id,
            'branch_id' => $branchUnderOrg2->id,
        ]);

        $this->actingAs($staff)->postJson('/api/leads', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_3_reject_nonexistent_organization(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $payload = $this->baseLeadData([
            'organization_id' => 999999,
        ]);

        $this->actingAs($staff)->postJson('/api/leads', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_4_reject_nonexistent_branch(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $org = Organization::create(['organization_code' => 'ORG_1', 'organization_name' => 'Org 1', 'is_active' => true]);

        $payload = $this->baseLeadData([
            'organization_id' => $org->id,
            'branch_id' => 888888,
        ]);

        $this->actingAs($staff)->postJson('/api/leads', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_5_reject_inactive_organization_for_new_assignment(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $inactiveOrg = Organization::create([
            'organization_code' => 'ORG_INACTIVE',
            'organization_name' => 'Inactive Org',
            'is_active' => false,
        ]);

        $payload = $this->baseLeadData([
            'organization_id' => $inactiveOrg->id,
        ]);

        $this->actingAs($staff)->postJson('/api/leads', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_6_reject_inactive_branch_for_new_assignment(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $activeOrg = Organization::create([
            'organization_code' => 'ORG_ACT',
            'organization_name' => 'Active Org',
            'is_active' => true,
        ]);

        $inactiveBranch = $activeOrg->branches()->create([
            'branch_code' => 'BR_INACT',
            'branch_name' => 'Inactive Branch',
            'is_active' => false,
        ]);

        $payload = $this->baseLeadData([
            'organization_id' => $activeOrg->id,
            'branch_id' => $inactiveBranch->id,
        ]);

        $this->actingAs($staff)->postJson('/api/leads', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_7_create_lead_without_organization_and_branch_for_backward_compatibility(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $payload = $this->baseLeadData([
            'organization_id' => null,
            'branch_id' => null,
        ]);

        $response = $this->actingAs($staff)->postJson('/api/leads', $payload)
            ->assertCreated()
            ->assertJsonPath('data.organization_id', null)
            ->assertJsonPath('data.branch_id', null);

        $leadId = $response->json('data.id');
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'organization_id' => null,
            'branch_id' => null,
        ]);
    }

    public function test_8_update_existing_lead_with_organization_and_branch(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        // Lead initially created without org/branch
        $lead = Lead::create($this->baseLeadData());
        $this->assertNull($lead->organization_id);

        $org = Organization::create([
            'organization_code' => 'ORG_NEW',
            'organization_name' => 'New Assigned Org',
            'is_active' => true,
        ]);

        $branch = $org->branches()->create([
            'branch_code' => 'BR_NEW',
            'branch_name' => 'New Assigned Branch',
            'is_active' => true,
        ]);

        $updatePayload = array_merge($this->baseLeadData(), [
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'follow_up_date' => $lead->follow_up_date->toDateString(),
        ]);

        $this->actingAs($staff)->putJson("/api/leads/{$lead->id}", $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.organization_id', $org->id)
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.organization.organization_code', 'ORG_NEW')
            ->assertJsonPath('data.branch.branch_code', 'BR_NEW');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_9_filter_leads_by_organization_and_branch(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $org1 = Organization::create(['organization_code' => 'ORG_F1', 'organization_name' => 'Org Filter 1', 'is_active' => true]);
        $branch1 = $org1->branches()->create(['branch_code' => 'BR_F1', 'branch_name' => 'Branch F1', 'is_active' => true]);
        $branch2 = $org1->branches()->create(['branch_code' => 'BR_F2', 'branch_name' => 'Branch F2', 'is_active' => true]);

        $org2 = Organization::create(['organization_code' => 'ORG_F2', 'organization_name' => 'Org Filter 2', 'is_active' => true]);
        $branch3 = $org2->branches()->create(['branch_code' => 'BR_F3', 'branch_name' => 'Branch F3', 'is_active' => true]);

        $lead1 = Lead::create($this->baseLeadData(['customer_name' => 'Lead One', 'organization_id' => $org1->id, 'branch_id' => $branch1->id]));
        $lead2 = Lead::create($this->baseLeadData(['customer_name' => 'Lead Two', 'organization_id' => $org1->id, 'branch_id' => $branch2->id]));
        $lead3 = Lead::create($this->baseLeadData(['customer_name' => 'Lead Three', 'organization_id' => $org2->id, 'branch_id' => $branch3->id]));
        $lead4 = Lead::create($this->baseLeadData(['customer_name' => 'Lead Unassigned', 'organization_id' => null, 'branch_id' => null]));

        // Filter by org1
        $resOrg1 = $this->actingAs($staff)->getJson("/api/leads?organization_id={$org1->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $namesOrg1 = collect($resOrg1->json('data'))->pluck('customer_name')->all();
        $this->assertContains('Lead One', $namesOrg1);
        $this->assertContains('Lead Two', $namesOrg1);
        $this->assertNotContains('Lead Three', $namesOrg1);
        $this->assertNotContains('Lead Unassigned', $namesOrg1);

        // Filter by org1 + branch2
        $resBranch2 = $this->actingAs($staff)->getJson("/api/leads?organization_id={$org1->id}&branch_id={$branch2->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->assertSame('Lead Two', $resBranch2->json('data.0.customer_name'));

        // Filter by org2
        $resOrg2 = $this->actingAs($staff)->getJson("/api/leads?organization_id={$org2->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->assertSame('Lead Three', $resOrg2->json('data.0.customer_name'));
    }

    public function test_10_lead_form_views_render_organization_and_branch_elements(): void
    {
        $this->seed();
        $staff = User::factory()->create();
        $lead = Lead::create($this->baseLeadData());

        // Create view
        $this->actingAs($staff)->get('/')
            ->assertOk()
            ->assertSee('data-orgs-url', false)
            ->assertSee('data-branches-url', false)
            ->assertSee('name="organization_id"', false)
            ->assertSee('name="branch_id"', false);

        // Edit view
        $this->actingAs($staff)->get("/leads/{$lead->id}/edit")
            ->assertOk()
            ->assertSee('data-orgs-url', false)
            ->assertSee('data-branches-url', false)
            ->assertSee('name="organization_id"', false)
            ->assertSee('name="branch_id"', false);
    }

    public function test_11_lead_list_view_renders_organization_and_branch_filters_and_column(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/leads')
            ->assertOk()
            ->assertSee('data-orgs-url', false)
            ->assertSee('data-branches-url', false)
            ->assertSee('id="filterOrgSelect"', false)
            ->assertSee('id="filterBranchSelect"', false)
            ->assertSee('Branch / Dealer');
    }
}
