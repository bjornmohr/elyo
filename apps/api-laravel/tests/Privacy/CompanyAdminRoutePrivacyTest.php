<?php

namespace Tests\Privacy;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use LogicException;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Support\HealthLeakAssertions;
use Tests\Support\PrivacyRouteRequestFactory;
use Tests\Support\PrivacySeeder;
use Tests\TestCase;

class CompanyAdminRoutePrivacyTest extends TestCase
{
    use HealthLeakAssertions;

    private const MIN_COMPANY_ROUTES = 24;

    private const MIN_ADMIN_ROUTES = 24;

    public function test_new_company_route_without_request_definition_is_rejected(): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();
        $route = new Route(
            ['GET'],
            'api/company/future-privacy-route',
            fn (): array => ['status' => 'synthetic'],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('GET api/company/future-privacy-route');

        (new PrivacyRouteRequestFactory)->forRoute(
            $route,
            $fixtures->users[Role::COMPANY_ADMIN->value],
            $fixtures,
        );
    }

    public function test_server_error_payload_is_leak_checked_before_status_failure(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['mood' => 4],
        ], 500));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('wellbeing_dimension');

        $this->assertSweptResponseIsSafe(
            $response,
            'GET',
            '/api/company/dashboard',
            [],
        );
    }

    public function test_company_threshold_below_platform_minimum_does_not_release_survey_results(): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();

        $responseIds = DB::connection('identity')
            ->table('survey_responses')
            ->where('survey_id', $fixtures->survey->id)
            ->orderBy('id')
            ->limit(5)
            ->pluck('id');

        DB::connection('identity')
            ->table('survey_answers')
            ->whereIn('response_id', $responseIds)
            ->delete();
        DB::connection('identity')
            ->table('survey_responses')
            ->whereIn('id', $responseIds)
            ->delete();

        $response = $this->actingAs(
            $fixtures->users[Role::COMPANY_ADMIN->value],
            'sanctum',
        )
            ->getJson('/api/company/surveys/'.$fixtures->survey->id.'/results')
            ->assertStatus(403)
            ->assertJsonPath('minRequired', 10)
            ->assertJsonPath('isAboveThreshold', false);

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/surveys/{id}/results',
            $fixtures->healthSubjectIds(),
        );
    }

    public function test_company_threshold_below_platform_minimum_does_not_release_measure_participation(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::COMPANY_ADMIN,
        ]);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
        ]);
        $employees = User::factory()->count(10)->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);

        $employees->take(5)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => $employee->team_id,
        ]));

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertOk()
            ->assertJsonPath('data.isAboveThreshold', false)
            ->assertJsonPath('data.eligibleCount', null)
            ->assertJsonPath('data.participantCount', null)
            ->assertJsonPath('data.participationRate', null)
            ->assertJsonPath('data.suppressionReason', 'ANONYMITY_THRESHOLD_NOT_MET');

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/measures/{id}/participation-summary',
        );
    }

    public function test_every_company_and_admin_route_response_is_free_of_health_leak_patterns(): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();
        $requestFactory = new PrivacyRouteRequestFactory;

        $companyRoutes = $this->routesUnder('api/company', $requestFactory);
        $adminRoutes = $this->routesUnder('api/admin', $requestFactory);

        $this->assertTrue(
            count($companyRoutes) >= self::MIN_COMPANY_ROUTES,
            'Company route sweep shrank below its minimum route-count guard.',
        );
        $this->assertTrue(
            count($adminRoutes) >= self::MIN_ADMIN_ROUTES,
            'Admin route sweep shrank below its minimum route-count guard.',
        );

        $this->sweepRoutes(
            $companyRoutes,
            [
                $fixtures->users[Role::COMPANY_OWNER->value],
                $fixtures->users[Role::COMPANY_ADMIN->value],
                $fixtures->users[Role::COMPANY_MANAGER->value],
            ],
            $fixtures,
            $requestFactory,
        );
        $this->sweepRoutes(
            $adminRoutes,
            [
                $fixtures->users[Role::ELYO_ADMIN->value],
                $fixtures->users[Role::ELYO_SUPPORT->value],
            ],
            $fixtures,
            $requestFactory,
        );
    }

    /**
     * @return list<Route>
     */
    private function routesUnder(string $prefix, PrivacyRouteRequestFactory $requestFactory): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => Str::startsWith($route->uri(), $prefix))
            ->sortBy(fn (Route $route): string => $requestFactory->sortKey($route))
            ->values()
            ->all();
    }

    /**
     * @param  list<Route>  $routes
     * @param  list<User>  $users
     */
    private function sweepRoutes(
        array $routes,
        array $users,
        PrivacySeeder $fixtures,
        PrivacyRouteRequestFactory $requestFactory,
    ): void {
        foreach ($routes as $route) {
            $successfulResponseObserved = false;

            foreach ($users as $user) {
                $request = $requestFactory->forRoute($route, $user, $fixtures);
                $response = $this->actingAs($user, 'sanctum')
                    ->json($request['method'], '/'.$request['uri'], $request['payload']);
                $successfulResponseObserved = $successfulResponseObserved
                    || ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300);

                $this->assertSweptResponseIsSafe(
                    $response,
                    $request['method'],
                    '/'.$route->uri(),
                    $fixtures->healthSubjectIds(),
                );
            }

            $this->assertTrue(
                $successfulResponseObserved,
                "{$requestFactory->forRoute($route, $users[0], $fixtures)['method']} /{$route->uri()} "
                .'never reached a successful response during the privacy route sweep.',
            );
        }
    }

    /**
     * @param  list<string>  $knownHealthSubjectIds
     */
    private function assertSweptResponseIsSafe(
        TestResponse $response,
        string $method,
        string $endpoint,
        array $knownHealthSubjectIds,
    ): void {
        $this->assertResponseHasNoHealthLeaks(
            $response,
            $endpoint,
            $knownHealthSubjectIds,
        );
        $this->assertTrue(
            $response->getStatusCode() < 500,
            "{$method} {$endpoint} returned a server error during the privacy route sweep.",
        );
    }
}
