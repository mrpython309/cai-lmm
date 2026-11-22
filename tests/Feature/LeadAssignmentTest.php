<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LeadAssignmentTest extends TestCase
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
            'location' => 'Guindy, Chennai',
            'customer_name' => 'Suresh Raina',
            'mobile' => '9840112233',
            'email' => 'suresh@example.com',
            'application' => 'Passenger',
            'vehicle_category' => '3 Wheeler',
            'hotspot' => 'Railway stations and bus stands',
            'current_vehicle' => 'None',
            'exchange' => 'No',
            'recommended_model' => 'Treo',
            'test_drive' => 'Yes',
            'finance' => 'Yes',
            'priority' => 'HOT',
            'follow_up_date' => today()->addDays(3)->toDateString(),
            'remarks' => 'Assignment test lead',
        ], $overrides);
    }

    public function test_assignment_endpoints_require_authentication(): void
    {
        $this->seed();
        $lead = Lead::create($this->baseLeadData());

        $this->getJson("/api/leads/{$lead->id}/assignments")->assertUnauthorized();
        $this->postJson("/api/leads/{$lead->id}/assign", ['employee_id' => 1])->assertUnauthorized();
    }

    public function test_assign_lead_to_employee_and_maintain_history(): void
    {
        $this->seed();
        $manager = User::factory()->create(['name' => 'Sales Manager']);

        $org = Organization::create([
            'organization_code' => 'CAI_GRP',
            'organization_name' => 'CAI Automotive',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'branch_code' => 'BR_CHN',
            'branch_name' => 'Chennai Branch',
            'is_active' => true,
        ]);

        $emp1 = Employee::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'EMP_01',
            'name' => 'Vignesh Kumar',
            'email' => 'vignesh@caimotors.com',
            'mobile_number' => '9876543210',
            'designation' => 'Sales Consultant',
            'is_active' => true,
        ]);

        $emp2 = Employee::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'EMP_02',
            'name' => 'Priya Dharshini',
            'email' => 'priya@caimotors.com',
            'mobile_number' => '9876543211',
            'designation' => 'Senior Consultant',
            'is_active' => true,
        ]);

        $lead = Lead::create($this->baseLeadData([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
        ]));

        $this->assertNull($lead->assigned_employee_id);

        // 1. Initial assignment to Emp 1
        $response = $this->actingAs($manager)->postJson("/api/leads/{$lead->id}/assign", [
            'employee_id' => $emp1->id,
            'remarks' => 'Initial allocation for demo drive',
        ])->assertOk()
            ->assertJsonPath('data.assigned_employee_id', $emp1->id)
            ->assertJsonPath('data.assigned_employee.name', 'Vignesh Kumar');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'assigned_employee_id' => $emp1->id,
        ]);

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'previous_employee_id' => null,
            'new_employee_id' => $emp1->id,
            'assigned_by' => $manager->id,
            'remarks' => 'Initial allocation for demo drive',
        ]);

        // 2. Reassignment to Emp 2
        $this->actingAs($manager)->postJson("/api/leads/{$lead->id}/assign", [
            'employee_id' => $emp2->id,
            'remarks' => 'Reassigned due to workload balance',
        ])->assertOk()
            ->assertJsonPath('data.assigned_employee_id', $emp2->id)
            ->assertJsonPath('data.assigned_employee.name', 'Priya Dharshini');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'assigned_employee_id' => $emp2->id,
        ]);

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'previous_employee_id' => $emp1->id,
            'new_employee_id' => $emp2->id,
            'assigned_by' => $manager->id,
            'remarks' => 'Reassigned due to workload balance',
        ]);

        // 3. Fetch Assignment History
        $historyRes = $this->actingAs($manager)->getJson("/api/leads/{$lead->id}/assignments")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $historyRes);
        $this->assertSame($emp2->id, $historyRes[0]['new_employee_id']);
        $this->assertSame($emp1->id, $historyRes[0]['previous_employee_id']);
        $this->assertSame('Sales Manager', $historyRes[0]['assigned_by']);
        $this->assertSame('Reassigned due to workload balance', $historyRes[0]['remarks']);

        $this->assertSame($emp1->id, $historyRes[1]['new_employee_id']);
        $this->assertNull($historyRes[1]['previous_employee_id']);
    }

    public function test_reject_assigning_inactive_employee(): void
    {
        $this->seed();
        $manager = User::factory()->create();

        $org = Organization::create([
            'organization_code' => 'ORG_ACT',
            'organization_name' => 'Org Active',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'branch_code' => 'BR_ACT',
            'branch_name' => 'Branch Active',
            'is_active' => true,
        ]);

        $inactiveEmp = Employee::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'INACT_EMP',
            'name' => 'Inactive Guy',
            'email' => 'inactive@test.com',
            'mobile_number' => '9876543210',
            'designation' => 'Advisor',
            'is_active' => false,
        ]);

        $lead = Lead::create($this->baseLeadData([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
        ]));

        $this->actingAs($manager)->postJson("/api/leads/{$lead->id}/assign", [
            'employee_id' => $inactiveEmp->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_reject_assigning_employee_from_another_branch_or_org(): void
    {
        $this->seed();
        $manager = User::factory()->create();

        $orgA = Organization::create(['organization_code' => 'ORG_A', 'organization_name' => 'Org A', 'is_active' => true]);
        $branchA1 = Branch::create(['organization_id' => $orgA->id, 'branch_code' => 'BR_A1', 'branch_name' => 'Branch A1', 'is_active' => true]);
        $branchA2 = Branch::create(['organization_id' => $orgA->id, 'branch_code' => 'BR_A2', 'branch_name' => 'Branch A2', 'is_active' => true]);

        $orgB = Organization::create(['organization_code' => 'ORG_B', 'organization_name' => 'Org B', 'is_active' => true]);
        $branchB1 = Branch::create(['organization_id' => $orgB->id, 'branch_code' => 'BR_B1', 'branch_name' => 'Branch B1', 'is_active' => true]);

        // Employee belonging to Branch A2
        $empBranchA2 = Employee::create([
            'organization_id' => $orgA->id,
            'branch_id' => $branchA2->id,
            'employee_id' => 'EMP_A2',
            'name' => 'Employee A2',
            'email' => 'a2@test.com',
            'mobile_number' => '9876543210',
            'designation' => 'Advisor',
            'is_active' => true,
        ]);

        // Employee belonging to Org B
        $empOrgB = Employee::create([
            'organization_id' => $orgB->id,
            'branch_id' => $branchB1->id,
            'employee_id' => 'EMP_B1',
            'name' => 'Employee B1',
            'email' => 'b1@test.com',
            'mobile_number' => '9876543211',
            'designation' => 'Advisor',
            'is_active' => true,
        ]);

        // Lead belonging to Org A, Branch A1
        $lead = Lead::create($this->baseLeadData([
            'organization_id' => $orgA->id,
            'branch_id' => $branchA1->id,
        ]));

        // Attempt assigning employee from branch A2 (different branch)
        $this->actingAs($manager)->postJson("/api/leads/{$lead->id}/assign", [
            'employee_id' => $empBranchA2->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['employee_id']);

        // Attempt assigning employee from org B (different org)
        $this->actingAs($manager)->postJson("/api/leads/{$lead->id}/assign", [
            'employee_id' => $empOrgB->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['employee_id']);
    }

    public function test_filter_leads_by_assigned_employee(): void
    {
        $this->seed();
        $manager = User::factory()->create();

        $org = Organization::create(['organization_code' => 'ORG', 'organization_name' => 'Org', 'is_active' => true]);
        $branch = Branch::create(['organization_id' => $org->id, 'branch_code' => 'BR', 'branch_name' => 'Branch', 'is_active' => true]);

        $emp1 = Employee::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'E1',
            'name' => 'Emp One',
            'email' => 'e1@test.com',
            'mobile_number' => '9000000001',
            'designation' => 'DSE',
            'is_active' => true,
        ]);

        $emp2 = Employee::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'employee_id' => 'E2',
            'name' => 'Emp Two',
            'email' => 'e2@test.com',
            'mobile_number' => '9000000002',
            'designation' => 'DSE',
            'is_active' => true,
        ]);

        $lead1 = Lead::create($this->baseLeadData([
            'customer_name' => 'Customer for Emp 1',
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'assigned_employee_id' => $emp1->id,
        ]));

        $lead2 = Lead::create($this->baseLeadData([
            'customer_name' => 'Customer for Emp 2',
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'assigned_employee_id' => $emp2->id,
        ]));

        $leadUnassigned = Lead::create($this->baseLeadData([
            'customer_name' => 'Unassigned Customer',
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'assigned_employee_id' => null,
        ]));

        // Filter by Emp 1
        $resEmp1 = $this->actingAs($manager)->getJson("/api/leads?assigned_employee_id={$emp1->id}")
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $resEmp1);
        $this->assertSame('Customer for Emp 1', $resEmp1[0]['customer_name']);

        // Filter by Unassigned
        $resUnassigned = $this->actingAs($manager)->getJson('/api/leads?assigned_employee_id=unassigned')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $resUnassigned);
        $this->assertSame('Unassigned Customer', $resUnassigned[0]['customer_name']);
    }

    public function test_lead_views_render_assignment_controls(): void
    {
        $this->seed();
        $staff = User::factory()->create();

        // Listing view has filter, column, and assign dialog
        $this->actingAs($staff)->get('/leads')
            ->assertOk()
            ->assertSee('id="filterEmployeeSelect"', false)
            ->assertSee('id="assignDialog"', false)
            ->assertSee('data-employees-url', false)
            ->assertSee('Assigned employee');

        // Form view has assigned employee field
        $this->actingAs($staff)->get('/')
            ->assertOk()
            ->assertSee('name="assigned_employee_id"', false)
            ->assertSee('data-employees-url', false);
    }
}
