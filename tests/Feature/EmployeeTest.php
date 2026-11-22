<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class EmployeeTest extends TestCase
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

    public function test_employee_endpoints_require_authentication(): void
    {
        $this->get('/employees')->assertRedirect('/login');
        $this->getJson('/api/employees')->assertUnauthorized();
        $this->postJson('/api/employees', [])->assertUnauthorized();
        $this->getJson('/api/employees/1')->assertUnauthorized();
        $this->putJson('/api/employees/1', [])->assertUnauthorized();
        $this->deleteJson('/api/employees/1')->assertUnauthorized();
    }

    public function test_employee_crud_and_validation(): void
    {
        $staff = User::factory()->create();

        $org = Organization::create([
            'organization_code' => 'ORG001',
            'organization_name' => 'CAI Motors Group',
            'organization_type' => 'Dealer',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'branch_code' => 'BR001',
            'branch_name' => 'Chennai Central',
            'is_active' => true,
        ]);

        // 1. Create Employee
        $response = $this->actingAs($staff)->postJson('/api/employees', [
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'EMP1001',
            'name' => 'Karthik Raja',
            'email' => 'karthik@caimotors.com',
            'mobile_number' => '9876543210',
            'designation' => 'Senior Sales Consultant',
            'is_active' => true,
        ])->assertCreated()
            ->assertHeader('Location')
            ->assertJsonPath('data.employee_id', 'EMP1001')
            ->assertJsonPath('data.name', 'Karthik Raja')
            ->assertJsonPath('data.email', 'karthik@caimotors.com')
            ->assertJsonPath('data.mobile_number', '9876543210')
            ->assertJsonPath('data.designation', 'Senior Sales Consultant')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.organization.organization_code', 'ORG001')
            ->assertJsonPath('data.branch.branch_code', 'BR001');

        $empId = $response->json('data.id');
        $this->assertDatabaseHas('employees', [
            'id' => $empId,
            'employee_id' => 'EMP1001',
            'email' => 'karthik@caimotors.com',
        ]);

        // 1b. Test validation errors on empty payload
        $this->actingAs($staff)->postJson('/api/employees', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'organization_id',
                'branch_id',
                'employee_id',
                'name',
                'email',
                'mobile_number',
                'designation',
            ]);

        // 2. Reject Duplicate employee_id
        $this->actingAs($staff)->postJson('/api/employees', [
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'EMP1001',
            'name' => 'Another Name',
            'email' => 'other@caimotors.com',
            'mobile_number' => '9876543211',
            'designation' => 'Sales Consultant',
        ])->assertUnprocessable()->assertJsonValidationErrors(['employee_id']);

        // 3. Reject Duplicate email
        $this->actingAs($staff)->postJson('/api/employees', [
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'EMP1002',
            'name' => 'Another Name',
            'email' => 'karthik@caimotors.com',
            'mobile_number' => '9876543211',
            'designation' => 'Sales Consultant',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        // 4. Show Employee
        $this->actingAs($staff)->getJson("/api/employees/{$empId}")
            ->assertOk()
            ->assertJsonPath('data.id', $empId)
            ->assertJsonPath('data.employee_id', 'EMP1001');

        // 5. Update Employee (can keep same employee_id and email)
        $this->actingAs($staff)->putJson("/api/employees/{$empId}", [
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'EMP1001',
            'name' => 'Karthik Raja Updated',
            'email' => 'karthik@caimotors.com',
            'mobile_number' => '9998887776',
            'designation' => 'Branch Sales Manager',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Karthik Raja Updated')
            ->assertJsonPath('data.designation', 'Branch Sales Manager')
            ->assertJsonPath('data.mobile_number', '9998887776')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('employees', [
            'id' => $empId,
            'name' => 'Karthik Raja Updated',
            'designation' => 'Branch Sales Manager',
            'is_active' => false,
        ]);

        // 6. Delete Employee
        $this->actingAs($staff)->deleteJson("/api/employees/{$empId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('employees', ['id' => $empId]);
    }

    public function test_reject_branch_belonging_to_another_organization(): void
    {
        $staff = User::factory()->create();

        $org1 = Organization::create([
            'organization_code' => 'ORG1',
            'organization_name' => 'Org One',
            'is_active' => true,
        ]);

        $org2 = Organization::create([
            'organization_code' => 'ORG2',
            'organization_name' => 'Org Two',
            'is_active' => true,
        ]);

        $branchOfOrg2 = Branch::create([
            'organization_id' => $org2->id,
            'branch_code' => 'BR2',
            'branch_name' => 'Branch Two',
            'is_active' => true,
        ]);

        $this->actingAs($staff)->postJson('/api/employees', [
            'organization_id' => $org1->id,
            'branch_id' => $branchOfOrg2->id,
            'employee_id' => 'EMP2001',
            'name' => 'Misplaced Employee',
            'email' => 'misplaced@test.com',
            'mobile_number' => '9876543210',
            'designation' => 'Executive',
        ])->assertUnprocessable()->assertJsonValidationErrors(['branch_id']);
    }

    public function test_reject_inactive_organization_and_branch_for_new_employee(): void
    {
        $staff = User::factory()->create();

        $inactiveOrg = Organization::create([
            'organization_code' => 'INACT_ORG',
            'organization_name' => 'Inactive Org',
            'is_active' => false,
        ]);

        $activeOrg = Organization::create([
            'organization_code' => 'ACT_ORG',
            'organization_name' => 'Active Org',
            'is_active' => true,
        ]);

        $inactiveBranch = Branch::create([
            'organization_id' => $activeOrg->id,
            'branch_code' => 'INACT_BR',
            'branch_name' => 'Inactive Branch',
            'is_active' => false,
        ]);

        // Reject inactive org
        $this->actingAs($staff)->postJson('/api/employees', [
            'organization_id' => $inactiveOrg->id,
            'branch_id' => 1,
            'employee_id' => 'EMP3001',
            'name' => 'Test Employee',
            'email' => 'test1@test.com',
            'mobile_number' => '9876543210',
            'designation' => 'Consultant',
        ])->assertUnprocessable()->assertJsonValidationErrors(['organization_id']);

        // Reject inactive branch
        $this->actingAs($staff)->postJson('/api/employees', [
            'organization_id' => $activeOrg->id,
            'branch_id' => $inactiveBranch->id,
            'employee_id' => 'EMP3002',
            'name' => 'Test Employee',
            'email' => 'test2@test.com',
            'mobile_number' => '9876543210',
            'designation' => 'Consultant',
        ])->assertUnprocessable()->assertJsonValidationErrors(['branch_id']);
    }

    public function test_employee_search_filtering_and_pagination(): void
    {
        $staff = User::factory()->create();

        $orgA = Organization::create(['organization_code' => 'OA', 'organization_name' => 'Org A', 'is_active' => true]);
        $orgB = Organization::create(['organization_code' => 'OB', 'organization_name' => 'Org B', 'is_active' => true]);

        $branchA1 = Branch::create(['organization_id' => $orgA->id, 'branch_code' => 'BA1', 'branch_name' => 'Branch A1', 'is_active' => true]);
        $branchB1 = Branch::create(['organization_id' => $orgB->id, 'branch_code' => 'BB1', 'branch_name' => 'Branch B1', 'is_active' => true]);

        Employee::create([
            'organization_id' => $orgA->id,
            'branch_id' => $branchA1->id,
            'employee_id' => 'EMP_A1',
            'name' => 'Aravind Kumar',
            'email' => 'aravind@orga.com',
            'mobile_number' => '9111111111',
            'designation' => 'Team Lead',
            'is_active' => true,
        ]);

        Employee::create([
            'organization_id' => $orgA->id,
            'branch_id' => $branchA1->id,
            'employee_id' => 'EMP_A2',
            'name' => 'Deepak Verma',
            'email' => 'deepak@orga.com',
            'mobile_number' => '9222222222',
            'designation' => 'Sales Consultant',
            'is_active' => false,
        ]);

        Employee::create([
            'organization_id' => $orgB->id,
            'branch_id' => $branchB1->id,
            'employee_id' => 'EMP_B1',
            'name' => 'Bala Sundaram',
            'email' => 'bala@orgb.com',
            'mobile_number' => '9333333333',
            'designation' => 'General Manager',
            'is_active' => true,
        ]);

        // Filter by organization
        $resOrgA = $this->actingAs($staff)->getJson("/api/employees?organization_id={$orgA->id}")
            ->assertOk()
            ->json('data');
        $this->assertCount(2, $resOrgA);

        // Filter by branch
        $resBranchB1 = $this->actingAs($staff)->getJson("/api/employees?branch_id={$branchB1->id}")
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $resBranchB1);
        $this->assertSame('EMP_B1', $resBranchB1[0]['employee_id']);

        // Filter by active status
        $resActive = $this->actingAs($staff)->getJson('/api/employees?is_active=1')
            ->assertOk()
            ->json('data');
        $this->assertCount(2, $resActive);

        $resInactive = $this->actingAs($staff)->getJson('/api/employees?is_active=0')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $resInactive);
        $this->assertSame('EMP_A2', $resInactive[0]['employee_id']);

        // Search by keyword (name)
        $resSearchName = $this->actingAs($staff)->getJson('/api/employees?search=Aravind')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $resSearchName);
        $this->assertSame('Aravind Kumar', $resSearchName[0]['name']);

        // Search by keyword (designation)
        $resSearchDesig = $this->actingAs($staff)->getJson('/api/employees?search=General%20Manager')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $resSearchDesig);
        $this->assertSame('Bala Sundaram', $resSearchDesig[0]['name']);
    }

    public function test_employee_web_view_renders_for_authenticated_user(): void
    {
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)->get('/employees')
            ->assertOk()
            ->assertSee('Employees')
            ->assertSee('Filter &amp; Search Employees', false)
            ->assertSee('id="employeeTable"', false)
            ->assertSee('id="employeeDialog"', false);
    }
}
