<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class EndToEndOrganizationBranchWorkflowTest extends TestCase
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

    public function test_complete_end_to_end_phase1_workflow(): void
    {
        // -------------------------------------------------------------------
        // 1. Authentication & UI View Access
        // -------------------------------------------------------------------
        $this->get('/organizations')->assertRedirect(route('login'));
        $this->get('/branches')->assertRedirect(route('login'));

        $staff = User::factory()->create([
            'email' => 'staff_e2e@cai.com',
            'password' => 'Password@123',
        ]);

        // Login session
        $this->actingAs($staff);

        $orgView = $this->get('/organizations')->assertOk();
        $orgView->assertSee('Organizations');
        $orgView->assertSee('organization-list.js');
        $orgView->assertSee('Dealer organizations');

        $branchView = $this->get('/branches')->assertOk();
        $branchView->assertSee('Branches');
        $branchView->assertSee('branch-list.js');
        $branchView->assertSee('Dynamic Organization & Branch Lookup');

        // -------------------------------------------------------------------
        // 2. Organization CRUD & Validation
        // -------------------------------------------------------------------
        // Validation check: empty required fields
        $this->postJson('/api/organizations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_code', 'organization_name']);

        // Create Active Organization 1
        $createOrg1 = $this->postJson('/api/organizations', [
            'organization_code' => 'ORG-E2E-1',
            'organization_name' => 'Mahindra E2E Dealer Group',
            'organization_type' => 'Dealer',
            'is_active' => true,
        ])->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.organization_code', 'ORG-E2E-1')
            ->assertJsonPath('data.is_active', true);

        $org1Id = $createOrg1->json('data.id');
        $this->assertDatabaseHas('organizations', [
            'id' => $org1Id,
            'organization_code' => 'ORG-E2E-1',
            'is_active' => true,
        ]);

        // Create Inactive Organization 2
        $createOrg2 = $this->postJson('/api/organizations', [
            'organization_code' => 'ORG-E2E-2',
            'organization_name' => 'Inactive Distributor Org',
            'organization_type' => 'Distributor',
            'is_active' => false,
        ])->assertCreated()
            ->assertJsonPath('data.is_active', false);

        $org2Id = $createOrg2->json('data.id');

        // Duplicate code validation
        $this->postJson('/api/organizations', [
            'organization_code' => 'ORG-E2E-1',
            'organization_name' => 'Duplicate Org Name',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_code']);

        // Filter: Active only vs Inactive only vs All
        $activeOrgs = $this->getJson('/api/organizations?active=1&all=1')->assertOk();
        $this->assertCount(1, $activeOrgs->json('data'));
        $this->assertSame('ORG-E2E-1', $activeOrgs->json('data.0.organization_code'));

        $inactiveOrgs = $this->getJson('/api/organizations?is_active=0')->assertOk();
        $this->assertCount(1, $inactiveOrgs->json('data'));
        $this->assertSame('ORG-E2E-2', $inactiveOrgs->json('data.0.organization_code'));

        $allOrgs = $this->getJson('/api/organizations')->assertOk();
        $this->assertCount(2, $allOrgs->json('data'));

        // Update Organization 1
        $this->putJson("/api/organizations/{$org1Id}", [
            'organization_code' => 'ORG-E2E-1',
            'organization_name' => 'Mahindra E2E Dealer Group Updated',
            'organization_type' => 'Distributor',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.organization_name', 'Mahindra E2E Dealer Group Updated')
            ->assertJsonPath('data.organization_type', 'Distributor');

        // -------------------------------------------------------------------
        // 3. Branch CRUD & Cascading Lookup
        // -------------------------------------------------------------------
        // Validation check: empty required fields
        $this->postJson('/api/branches', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id', 'branch_code', 'branch_name']);

        // Create Branch 1 under Org 1
        $createBranch1 = $this->postJson('/api/branches', [
            'organization_id' => $org1Id,
            'branch_code' => 'BR-E2E-1',
            'branch_name' => 'Chennai Central Hub',
            'city' => 'Chennai',
            'state' => 'Tamil Nadu',
            'pincode' => '600001',
            'contact_person' => 'Ramesh',
            'contact_number' => '9876543210',
            'email' => 'chennai@example.com',
            'is_active' => true,
        ])->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.branch_code', 'BR-E2E-1')
            ->assertJsonPath('data.organization.organization_code', 'ORG-E2E-1');

        $branch1Id = $createBranch1->json('data.id');

        // Create Branch 2 under Org 1
        $createBranch2 = $this->postJson('/api/branches', [
            'organization_id' => $org1Id,
            'branch_code' => 'BR-E2E-2',
            'branch_name' => 'Coimbatore Showroom',
            'city' => 'Coimbatore',
            'state' => 'Tamil Nadu',
            'pincode' => '641001',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.branch_code', 'BR-E2E-2');

        $branch2Id = $createBranch2->json('data.id');

        // Duplicate branch code within same organization must fail
        $this->postJson('/api/branches', [
            'organization_id' => $org1Id,
            'branch_code' => 'BR-E2E-1',
            'branch_name' => 'Duplicate Branch Attempt',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_code']);

        // Same branch code in DIFFERENT organization is permitted
        $this->postJson('/api/branches', [
            'organization_id' => $org2Id,
            'branch_code' => 'BR-E2E-1',
            'branch_name' => 'Org2 Branch with BR-E2E-1 code',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.organization.organization_code', 'ORG-E2E-2');

        // Cascading Branch Query: filter branches by organization_id
        $org1Branches = $this->getJson("/api/branches?organization_id={$org1Id}&active=1&all=1")->assertOk();
        $this->assertCount(2, $org1Branches->json('data'));
        $this->assertEqualsCanonicalizing(
            ['BR-E2E-1', 'BR-E2E-2'],
            array_column($org1Branches->json('data'), 'branch_code')
        );

        // Update Branch 1
        $this->putJson("/api/branches/{$branch1Id}", [
            'organization_id' => $org1Id,
            'branch_code' => 'BR-E2E-1',
            'branch_name' => 'Chennai Central Main Hub',
            'city' => 'Chennai North',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.branch_name', 'Chennai Central Main Hub')
            ->assertJsonPath('data.city', 'Chennai North');

        // -------------------------------------------------------------------
        // 4. Deletion Restrictions & Lifecycle
        // -------------------------------------------------------------------
        // Deleting Org 1 while branches exist must fail with 422
        $delOrgBlocked = $this->deleteJson("/api/organizations/{$org1Id}")
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertStringContainsString('associated branches', $delOrgBlocked->json('message'));
        $this->assertDatabaseHas('organizations', ['id' => $org1Id]);

        // Delete branches under Org 1
        $this->deleteJson("/api/branches/{$branch1Id}")->assertNoContent();
        $this->deleteJson("/api/branches/{$branch2Id}")->assertNoContent();
        $this->assertDatabaseMissing('branches', ['id' => $branch1Id]);
        $this->assertDatabaseMissing('branches', ['id' => $branch2Id]);

        // Now Org 1 can be deleted
        $this->deleteJson("/api/organizations/{$org1Id}")->assertNoContent();
        $this->assertDatabaseMissing('organizations', ['id' => $org1Id]);

        // -------------------------------------------------------------------
        // 5. Existing Lead Workflow Regression
        // -------------------------------------------------------------------
        $this->seed();
        $leadResponse = $this->postJson('/api/leads', [
            'lead_source' => 'Digital',
            'business_type' => 'Individual',
            'location' => 'Velachery, Chennai',
            'customer_name' => 'Senthil Nathan',
            'mobile' => '9840123456',
            'email' => 'senthil@example.com',
            'application' => 'Cargo',
            'vehicle_category' => '3 Wheeler',
            'hotspot' => 'Warehouses and dark stores',
            'current_vehicle' => 'None',
            'exchange' => 'No',
            'recommended_model' => 'Zor Grand',
            'test_drive' => 'Yes',
            'finance' => 'Yes',
            'priority' => 'HOT',
            'follow_up_date' => today()->addDays(7)->toDateString(),
            'remarks' => 'E2E regression lead test',
        ])->assertCreated();

        $leadId = $leadResponse->json('data.id');
        $this->assertDatabaseHas('leads', ['id' => $leadId, 'customer_name' => 'Senthil Nathan']);

        $this->getJson('/api/leads')->assertOk()->assertJsonPath('meta.total', 1);

        $this->deleteJson("/api/leads/{$leadId}")->assertNoContent();
        $this->assertDatabaseMissing('leads', ['id' => $leadId]);
    }
}
