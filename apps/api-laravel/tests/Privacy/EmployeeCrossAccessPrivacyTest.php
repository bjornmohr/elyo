<?php

namespace Tests\Privacy;

use Tests\Support\HealthLeakAssertions;
use Tests\Support\PrivacySeeder;
use Tests\TestCase;

class EmployeeCrossAccessPrivacyTest extends TestCase
{
    use HealthLeakAssertions;

    public function test_employee_collections_exclude_another_employees_wellbeing_and_lab_data(): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();

        $this->actingAs($fixtures->employee, 'sanctum')
            ->getJson('/api/employee/history')
            ->assertStatus(200)
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.id', $fixtures->ownWellbeing->id);

        $this->actingAs($fixtures->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fixtures->ownLabReading->id);
    }

    public function test_foreign_lab_resource_identifier_is_not_found(): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();

        $response = $this->actingAs($fixtures->employee, 'sanctum')
            ->deleteJson('/api/employee/lab-markers/'.$fixtures->foreignLabReading->id);

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/employee/lab-markers/{reading}',
            $fixtures->healthSubjectIds(),
        );

        $response
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'LAB_READING_NOT_FOUND');
    }
}
