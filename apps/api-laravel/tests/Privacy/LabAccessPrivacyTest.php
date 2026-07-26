<?php

namespace Tests\Privacy;

use App\Enums\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Support\HealthLeakAssertions;
use Tests\Support\PrivacySeeder;
use Tests\TestCase;

class LabAccessPrivacyTest extends TestCase
{
    use HealthLeakAssertions;

    private const MIN_LAB_ROUTES = 4;

    /**
     * @return array<string, array{Role}>
     */
    public static function forbiddenRoles(): array
    {
        return [
            'company owner' => [Role::COMPANY_OWNER],
            'company admin' => [Role::COMPANY_ADMIN],
            'company manager' => [Role::COMPANY_MANAGER],
            'platform admin' => [Role::ELYO_ADMIN],
            'platform support' => [Role::ELYO_SUPPORT],
            'partner' => [Role::PARTNER],
        ];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_company_admin_and_partner_roles_are_forbidden_on_every_lab_route(Role $role): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();
        $routes = $this->labRoutes();

        $this->assertTrue(
            count($routes) >= self::MIN_LAB_ROUTES,
            'Lab route sweep shrank below its minimum route-count guard.',
        );

        $user = $fixtures->users[$role->value];

        foreach ($routes as $route) {
            $method = $this->requestMethod($route);
            $uri = Str::replace(
                ['{markerKey}', '{reading}'],
                ['ferritin', $fixtures->foreignLabReading->id],
                $route->uri(),
            );
            $payload = $method === 'POST'
                ? ['markerKey' => 'ferritin', 'value' => 42.1, 'measuredAt' => '2026-07-20']
                : [];

            $response = $this->actingAs($user, 'sanctum')
                ->json($method, '/'.$uri, $payload);

            $this->assertResponseHasNoHealthLeaks(
                $response,
                '/'.$route->uri(),
                $fixtures->healthSubjectIds(),
            );

            $response
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    public function test_real_partner_principal_is_forbidden_on_every_lab_route(): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();
        $routes = $this->labRoutes();

        $this->assertTrue(
            count($routes) >= self::MIN_LAB_ROUTES,
            'Lab route sweep shrank below its minimum route-count guard.',
        );

        $token = $this->postJson('/api/partner/login', [
            'email' => $fixtures->partner->email,
            'password' => 'synthetic-partner-password',
        ])
            ->assertStatus(200)
            ->json('token');

        foreach ($routes as $route) {
            $method = $this->requestMethod($route);
            $uri = Str::replace(
                ['{markerKey}', '{reading}'],
                ['ferritin', $fixtures->foreignLabReading->id],
                $route->uri(),
            );
            $payload = $method === 'POST'
                ? ['markerKey' => 'ferritin', 'value' => 42.1, 'measuredAt' => '2026-07-20']
                : [];

            $response = $this->withToken($token)
                ->json($method, '/'.$uri, $payload);

            $this->assertResponseHasNoHealthLeaks(
                $response,
                '/'.$route->uri(),
                $fixtures->healthSubjectIds(),
            );

            $response
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    public function test_forbidden_lab_error_response_is_leak_checked(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'error' => [
                'code' => 'FORBIDDEN',
                'markerKey' => 'ferritin',
            ],
        ], 403));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.error.markerKey');

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/employee/lab-markers',
        );
    }

    /**
     * @return list<Route>
     */
    private function labRoutes(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(
                fn (Route $route): bool => Str::startsWith($route->uri(), 'api/employee/lab-markers'),
            )
            ->values()
            ->all();
    }

    private function requestMethod(Route $route): string
    {
        return collect($route->methods())
            ->first(fn (string $method): bool => ! in_array($method, ['HEAD', 'OPTIONS'], true))
            ?? 'GET';
    }
}
