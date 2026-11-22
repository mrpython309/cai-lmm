<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class OrganizationBranchTest extends TestCase
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

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/organizations')->assertUnauthorized();
        $this->postJson('/api/organizations', [])->assertUnauthorized();
        $this->getJson('/api/organizations/1')->assertUnauthorized();
        $this->putJson('/api/organizations/1', [])->assertUnauthorized();
        $this->deleteJson('/api/organizations/1')->assertUnauthorized();

        $this->getJson('/api/branches')->assertUnauthorized();
        $this->postJson('/api/branches', [])->assertUnauthorized();
        $this->getJson('/api/branches/1')->assertUnauthorized();
        $this->putJson('/api/branches/1', [])->assertUnauthorized();
        $this->deleteJson('/api/branches/1')->assertUnauthorized();
    }

    public function test_organization_crud_and_validation(): void
    {
        $staff = User::factory()->create();

        // 1. Create Organization
        $response = $this->actingAs($staff)->postJson('/api/organizations', [
            'organization_code' => 'ORG001',
            'organization_name' => 'Mahindra Dealer Group',
            'organization_type' => 'Dealer',
            'is_active' => true,
        ])->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.organization_code', 'ORG001')
            ->assertJsonPath('data.organization_name', 'Mahindra Dealer Group')
            ->assertJsonPath('data.is_active', true);

        $orgId = $response->json('data.id');
        $this->assertDatabaseHas('organizations', ['id' => $orgId, 'organization_code' => 'ORG001']);

        // 2. Duplicate organization_code validation
        $this->actingAs($staff)->postJson('/api/organizations', [
            'organization_code' => 'ORG001',
            'organization_name' => 'Another Org',
        ])->assertUnprocessable()->assertJsonValidationErrors(['organization_code']);

        // 3. Show Organization
        $this->actingAs($staff)->getJson("/api/organizations/{$orgId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orgId)
            ->assertJsonPath('data.branches_count', 0);

        // 4. Update Organization
        $this->actingAs($staff)->putJson("/api/organizations/{$orgId}", [
            'organization_code' => 'ORG001',
            'organization_name' => 'Mahindra Dealer Group Updated',
            'organization_type' => 'Distributor',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.organization_name', 'Mahindra Dealer Group Updated')
            ->assertJsonPath('data.organization_type', 'Distributor')
            ->assertJsonPath('data.is_active', false);

        // 5. Inactive exclusion & Active filtering
        $activeOrg = Organization::create([
            'organization_code' => 'ORG002',
            'organization_name' => 'Active Org',
            'is_active' => true,
        ]);

        $allList = $this->actingAs($staff)->getJson('/api/organizations')->assertOk();
        $this->assertCount(2, $allList->json('data'));

        $activeOnlyList = $this->actingAs($staff)->getJson('/api/organizations?active=1')->assertOk();
        $this->assertCount(1, $activeOnlyList->json('data'));
        $this->assertSame('ORG002', $activeOnlyList->json('data.0.organization_code'));
    }

    public function test_organization_deletion_restriction_with_branches(): void
    {
        $staff = User::factory()->create();

        $org = Organization::create([
            'organization_code' => 'ORG_DEL',
            'organization_name' => 'Delete Test Org',
            'is_active' => true,
        ]);

        $branch = $org->branches()->create([
            'branch_code' => 'BR_DEL',
            'branch_name' => 'Delete Test Branch',
            'is_active' => true,
        ]);

        // Attempting to delete organization with active branches must fail with 422
        $this->actingAs($staff)->deleteJson("/api/organizations/{$org->id}")
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('organizations', ['id' => $org->id]);

        // Delete the branch first
        $this->actingAs($staff)->deleteJson("/api/branches/{$branch->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);

        // Now deleting the organization succeeds with 204
        $this->actingAs($staff)->deleteJson("/api/organizations/{$org->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('organizations', ['id' => $org->id]);
    }

    public function test_branch_crud_and_composite_unique_validation(): void
    {
        $staff = User::factory()->create();

        $org1 = Organization::create([
            'organization_code' => 'ORG_1',
            'organization_name' => 'First Organization',
        ]);

        $org2 = Organization::create([
            'organization_code' => 'ORG_2',
            'organization_name' => 'Second Organization',
        ]);

        // 1. Create Branch under Org 1
        $createRes = $this->actingAs($staff)->postJson('/api/branches', [
            'organization_id' => $org1->id,
            'branch_code' => 'BR001',
            'branch_name' => 'Chennai Central',
            'city' => 'Chennai',
            'state' => 'Tamil Nadu',
            'pincode' => '600001',
            'contact_person' => 'Ramesh',
            'contact_number' => '9876543210',
            'email' => 'chennai@example.com',
            'is_active' => true,
        ])->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.branch_code', 'BR001')
            ->assertJsonPath('data.city', 'Chennai')
            ->assertJsonPath('data.organization.organization_code', 'ORG_1');

        $branch1Id = $createRes->json('data.id');

        // 2. Duplicate branch_code in SAME organization must fail
        $this->actingAs($staff)->postJson('/api/branches', [
            'organization_id' => $org1->id,
            'branch_code' => 'BR001',
            'branch_name' => 'Duplicate Branch',
        ])->assertUnprocessable()->assertJsonValidationErrors(['branch_code']);

        // 3. Same branch_code in DIFFERENT organization is ALLOWED
        $this->actingAs($staff)->postJson('/api/branches', [
            'organization_id' => $org2->id,
            'branch_code' => 'BR001',
            'branch_name' => 'Org2 Branch with same code',
            'is_active' => false,
        ])->assertCreated()
            ->assertJsonPath('data.organization.organization_code', 'ORG_2');

        // 4. Show Branch
        $this->actingAs($staff)->getJson("/api/branches/{$branch1Id}")
            ->assertOk()
            ->assertJsonPath('data.branch_name', 'Chennai Central');

        // 5. Update Branch (keeping same code within same org)
        $this->actingAs($staff)->putJson("/api/branches/{$branch1Id}", [
            'organization_id' => $org1->id,
            'branch_code' => 'BR001',
            'branch_name' => 'Chennai Central Updated',
            'city' => 'Chennai North',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.branch_name', 'Chennai Central Updated')
            ->assertJsonPath('data.city', 'Chennai North');

        // 6. Filter branches by organization_id
        $org1Branches = $this->actingAs($staff)->getJson("/api/branches?organization_id={$org1->id}")->assertOk();
        $this->assertCount(1, $org1Branches->json('data'));
        $this->assertSame('Chennai Central Updated', $org1Branches->json('data.0.branch_name'));

        // 7. Filter active branches
        $activeBranches = $this->actingAs($staff)->getJson('/api/branches?active=1')->assertOk();
        $this->assertCount(1, $activeBranches->json('data'));
        $this->assertSame('BR001', $activeBranches->json('data.0.branch_code'));

        // 8. Delete Branch
        $this->actingAs($staff)->deleteJson("/api/branches/{$branch1Id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('branches', ['id' => $branch1Id]);
    }

    public function test_ui_views_require_authentication_and_render_correctly(): void
    {
        // Unauthenticated access redirects to login
        $this->get('/organizations')->assertRedirect(route('login'));
        $this->get('/branches')->assertRedirect(route('login'));

        $staff = User::factory()->create();

        // Organizations view renders successfully
        $this->actingAs($staff)->get('/organizations')
            ->assertOk()
            ->assertSee('Organizations')
            ->assertSee('Dealer organizations')
            ->assertSee('organization-list.js');

        // Branches view renders successfully
        $this->actingAs($staff)->get('/branches')
            ->assertOk()
            ->assertSee('Branches')
            ->assertSee('Dealer branches')
            ->assertSee('Dynamic Organization & Branch Lookup')
            ->assertSee('branch-list.js');
    }
}
