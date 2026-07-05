<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\LabMarker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeLabMarkersTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_only_read_own_lab_markers_with_metadata(): void
    {
        $company = Company::factory()->create();
        $employee = $this->user($company, Role::EMPLOYEE);
        $otherEmployee = $this->user($company, Role::EMPLOYEE);

        LabMarker::create([
            'user_id' => $employee->id,
            'marker_key' => 'vitd',
            'value' => 18,
            'status' => LabMarker::STATUS_BELOW_RANGE,
            'is_highlighted' => true,
        ]);
        LabMarker::create([
            'user_id' => $otherEmployee->id,
            'marker_key' => 'crp',
            'value' => 9,
            'status' => LabMarker::STATUS_ABOVE_RANGE,
            'is_highlighted' => true,
        ]);

        $response = $this->actingAs($employee)
            ->getJson('/api/employee/lab-markers')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $response);
        $this->assertSame('vitd', $response[0]['markerKey']);
        $this->assertSame('Vitamin D', $response[0]['name']);
        $this->assertSame('ng/ml', $response[0]['unit']);
        $this->assertEquals(18.0, $response[0]['value']);
        $this->assertSame('unter Bereich', $response[0]['status']);
        $this->assertTrue($response[0]['isHighlighted']);
        $this->assertEquals(30.0, $response[0]['low']);
        $this->assertEquals(50.0, $response[0]['high']);
        $this->assertSame('mikro', $response[0]['group']);
    }

    public function test_company_and_admin_users_cannot_access_employee_lab_markers(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->user($company, Role::COMPANY_ADMIN))
            ->getJson('/api/employee/lab-markers')
            ->assertForbidden();

        $this->actingAs($this->user($company, Role::ELYO_ADMIN))
            ->getJson('/api/employee/lab-markers')
            ->assertForbidden();

        $this->actingAs($this->user($company, Role::COMPANY_ADMIN))
            ->getJson('/api/company/lab-markers')
            ->assertNotFound();

        $this->actingAs($this->user($company, Role::ELYO_ADMIN))
            ->getJson('/api/admin/lab-markers')
            ->assertNotFound();
    }

    private function user(Company $company, Role $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
        ]);
    }
}
