<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class LeadWorkflowTest extends TestCase
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

        throw new RuntimeException('Use in-memory SQLite or a dedicated MySQL database ending in _test. Tests rebuild its schema.');
    }

    public function test_dashboard_and_api_require_staff_authentication(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/leads')->assertRedirect(route('login'));
        $this->get('/api/leads')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->getJson('/api/lead-options')->assertUnauthorized();
        $this->postJson('/api/leads', [])->assertUnauthorized();
    }

    public function test_staff_account_provisioning_sign_in_and_sign_out(): void
    {
        $this->artisan('staff:create')
            ->expectsQuestion('Staff name', 'Sales Staff')
            ->expectsQuestion('Email address', 'STAFF@example.com')
            ->expectsQuestion('Password (8 or more characters)', 'Examp1!')
            ->expectsQuestion('Confirm password', 'Examp1!')
            ->expectsOutput('The password field must be at least 8 characters.')
            ->assertFailed();

        $this->artisan('staff:create')
            ->expectsQuestion('Staff name', 'Sales Staff')
            ->expectsQuestion('Email address', 'STAFF@example.com')
            ->expectsQuestion('Password (8 or more characters)', 'Examp1e!')
            ->expectsQuestion('Confirm password', 'Examp1e!')
            ->expectsOutput('Staff account created.')
            ->assertSuccessful();

        $staff = User::where('email', 'staff@example.com')->sole();
        $this->assertTrue(Hash::check('Examp1e!', $staff->password));

        $this->get('/login')->assertOk()->assertSee('Staff sign-in');
        $this->from('/login')->post('/login', ['email' => $staff->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'STAFF@example.com', 'password' => 'Examp1e!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($staff);
        $this->get('/')->assertOk()->assertSee('New Customer Registration')->assertSee('Remarks / customer requirement');
        $this->get('/leads')->assertOk()->assertSee('Registered Leads');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_catalog_seeds_the_original_choices_and_reads_changes_from_the_database(): void
    {
        $this->seed();
        $this->seed();
        $this->actingAs(User::factory()->create());
        $this->assertDatabaseCount('lead_options', 54);

        LeadOption::query()->insert([
            'field' => 'recommended_model', 'scope' => 'Cargo', 'value' => 'Test catalog entry', 'position' => 10,
        ]);

        $response = $this->getJson('/api/lead-options')->assertOk()
            ->assertJsonPath('data.business_type.0.requires_company', false)
            ->assertJsonPath('data.business_type.1.requires_company', true)
            ->assertJsonPath('data.business_type.2.requires_company', true)
            ->assertJsonPath('data.priority.0.description', 'Planning to buy within 0–30 days');
        $this->assertSame(['Digital', 'Field', 'Financier', 'Exchange', 'Referral', 'Fleet', 'Institutional'],
            array_column($response->json('data.lead_source'), 'value'));
        $this->assertSame(['Zor Grand', 'ZEO', 'Jeeto', 'Other', 'Test catalog entry'],
            collect($response->json('data.recommended_model'))->where('scope', 'Cargo')->pluck('value')->all());
        $this->assertSame(['Treo', 'UDO', 'e-Alfa', 'Alfa', 'Other'],
            collect($response->json('data.recommended_model'))->where('scope', 'Passenger')->pluck('value')->all());
        $this->assertSame(14, count($response->json('data.hotspot')));
    }

    public function test_create_view_update_list_and_delete_a_lead(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->create());

        $payload = $this->leadDetails();
        $created = $this->postJson('/api/leads', [...$payload, 'id' => 12345])
            ->assertCreated()->assertJsonFragment($payload);
        $id = $created->json('data.id');
        $url = "/api/leads/{$id}";
        $this->assertNotSame(12345, $id);
        $created->assertHeader('Location', url($url));
        $this->assertDatabaseHas('leads', Arr::except($payload, 'follow_up_date'));
        $this->assertSame($payload['follow_up_date'], Lead::findOrFail($id)->follow_up_date->toDateString());

        $this->getJson('/api/leads')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
        $this->getJson($url)->assertOk()->assertJsonFragment($payload);
        $this->get("/leads/{$id}/edit")->assertOk()->assertSee('Edit Customer Lead');

        $updated = array_replace($payload, [
            'business_type' => 'Individual', 'customer_name' => 'Updated Customer', 'application' => 'Cargo',
            'hotspot' => 'Courier hubs', 'recommended_model' => 'Jeeto',
        ]);
        unset($updated['email'], $updated['exchange'], $updated['finance']);
        $this->putJson($url, $updated)->assertOk()
            ->assertJsonPath('data.company_name', null)->assertJsonPath('data.email', null)
            ->assertJsonPath('data.exchange', null)->assertJsonPath('data.finance', null)
            ->assertJsonPath('data.customer_name', 'Updated Customer')->assertJsonPath('data.recommended_model', 'Jeeto');
        $this->assertDatabaseHas('leads', ['id' => $id, 'application' => 'Cargo', 'company_name' => null]);

        $this->deleteJson($url)->assertNoContent();
        $this->getJson($url)->assertNotFound();
        $this->putJson($url, $updated)->assertNotFound();
        $this->get("/leads/{$id}/edit")->assertNotFound();
        $this->getJson('/api/leads')->assertJsonPath('meta.total', 0);
        $this->assertDatabaseMissing('leads', ['id' => $id]);
    }

    public function test_validates_required_formats_catalog_values_and_conditional_choices(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/leads', [])->assertUnprocessable()->assertJsonValidationErrors([
            'lead_source', 'business_type', 'location', 'customer_name', 'mobile', 'application',
            'vehicle_category', 'hotspot', 'recommended_model', 'priority', 'follow_up_date',
        ]);

        $invalid = array_replace($this->leadDetails(), [
            'lead_source' => str_repeat('x', 121), 'recommended_model' => str_repeat('x', 121), 'business_type' => 'Logistics', 'company_name' => '',
            'mobile' => '5123456789', 'email' => 'invalid-email', 'vehicle_category' => 'Unknown category',
            'application' => 'Cargo', 'finance' => 'Later', 'priority' => 'LATER',
            'follow_up_date' => today()->subDay()->toDateString(), 'remarks' => str_repeat('a', 5001),
        ]);
        $this->postJson('/api/leads', $invalid)->assertUnprocessable()->assertJsonValidationErrors([
            'lead_source', 'company_name', 'mobile', 'email', 'vehicle_category', 'hotspot', 'recommended_model',
            'finance', 'priority', 'follow_up_date', 'remarks',
        ]);
        $this->postJson('/api/leads', array_replace($this->leadDetails(), ['application' => ['Cargo']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['application', 'hotspot']);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_an_overdue_follow_up_can_be_retained_but_a_new_date_must_be_current(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->create());
        $payload = $this->leadDetails();
        $id = $this->postJson('/api/leads', $payload)->assertCreated()->json('data.id');
        $this->travel(2)->days();

        $this->putJson("/api/leads/{$id}", [...$payload, 'remarks' => 'Follow-up pending'])
            ->assertOk()->assertJsonPath('data.follow_up_date', $payload['follow_up_date']);
        $this->putJson("/api/leads/{$id}", [...$payload, 'follow_up_date' => today()->subDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('follow_up_date');
        $this->putJson("/api/leads/{$id}", [...$payload, 'follow_up_date' => today()->toDateString()])
            ->assertOk()->assertJsonPath('data.follow_up_date', today()->toDateString());
    }

    public function test_lead_list_is_paginated_newest_first(): void
    {
        $this->actingAs(User::factory()->create());
        for ($index = 1; $index <= 16; $index++) {
            Lead::create([...$this->leadDetails(), 'customer_name' => "Customer {$index}"]);
        }

        $this->getJson('/api/leads')->assertOk()->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.customer_name', 'Customer 16');
        $this->getJson('/api/leads?page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_name', 'Customer 1');
    }

    private function leadDetails(): array
    {
        return [
            'lead_source' => 'Digital', 'business_type' => 'Company', 'company_name' => 'Sales Transport',
            'location' => 'Chennai', 'customer_name' => 'Sample Customer', 'mobile' => '9876543210',
            'email' => 'customer@example.com', 'application' => 'Passenger', 'vehicle_category' => '3 Wheeler',
            'hotspot' => 'Auto stands', 'current_vehicle' => 'Car', 'exchange' => 'Yes', 'recommended_model' => 'Treo',
            'test_drive' => 'No', 'finance' => 'Yes', 'priority' => 'HOT', 'follow_up_date' => today()->toDateString(),
            'remarks' => "Two vehicles requested.\nCall after 3 PM.",
        ];
    }
}
