<?php

namespace Tests\Support;

use App\Enums\Role;
use App\Models\SystemMeasureTemplateExercise;
use App\Models\User;
use Illuminate\Routing\Route;
use LogicException;

final class PrivacyRouteRequestFactory
{
    /**
     * Explicit successful-request definitions for every currently swept route.
     *
     * Dynamic discovery remains authoritative. A future route reaches
     * forRoute(), misses this list and fails until its synthetic request is
     * reviewed and added here.
     *
     * @var list<string>
     */
    private const ROUTE_DEFINITIONS = [
        'GET api/admin/companies',
        'POST api/admin/companies',
        'GET api/admin/companies/{company}',
        'PUT api/admin/companies/{company}',
        'POST api/admin/companies/{company}/invite-company-admin',
        'GET api/admin/partners',
        'PATCH api/admin/partners/{id}',
        'GET api/admin/points-config',
        'PUT api/admin/points-config',
        'GET api/admin/system-exercise-tags',
        'GET api/admin/system-exercises',
        'POST api/admin/system-exercises',
        'GET api/admin/system-exercises/{systemExercise}',
        'PATCH api/admin/system-exercises/{systemExercise}',
        'POST api/admin/system-exercises/{systemExercise}/archive',
        'GET api/admin/system-measure-templates',
        'POST api/admin/system-measure-templates',
        'GET api/admin/system-measure-templates/{systemMeasureTemplate}',
        'PATCH api/admin/system-measure-templates/{systemMeasureTemplate}',
        'POST api/admin/system-measure-templates/{systemMeasureTemplate}/archive',
        'POST api/admin/system-measure-templates/{systemMeasureTemplate}/exercises',
        'POST api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/reorder',
        'PATCH api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}',
        'DELETE api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}',
        'GET api/company/dashboard',
        'GET api/company/invitations',
        'POST api/company/invitations',
        'DELETE api/company/invitations/{invite}',
        'GET api/company/measures',
        'POST api/company/measures',
        'PATCH api/company/measures/{id}',
        'GET api/company/measures/{id}/participation-summary',
        'POST api/company/measures/{measure}/checkin-token',
        'GET api/company/reports',
        'GET api/company/surveys',
        'POST api/company/surveys',
        'GET api/company/surveys/{id}',
        'PATCH api/company/surveys/{id}',
        'DELETE api/company/surveys/{id}',
        'POST api/company/surveys/{id}/activate',
        'GET api/company/surveys/{id}/results',
        'GET api/company/teams',
        'POST api/company/teams',
        'GET api/company/teams/{id}',
        'PUT api/company/teams/{id}',
        'DELETE api/company/teams/{id}',
        'GET api/company/teams/{teamId}/members',
        'GET api/company/users',
    ];

    /**
     * @return array{method: string, uri: string, payload: array<string, mixed>}
     */
    public function forRoute(Route $route, User $user, PrivacySeeder $fixtures): array
    {
        $method = $this->method($route);
        $definition = "{$method} {$route->uri()}";

        if (! in_array($definition, self::ROUTE_DEFINITIONS, true)) {
            throw new LogicException(
                "Missing privacy route request definition for [{$definition}].",
            );
        }

        return [
            'method' => $method,
            'uri' => $this->resolveUri($route->uri(), $method, $fixtures),
            'payload' => $this->payload($method, $route->uri(), $user, $fixtures),
        ];
    }

    public function sortKey(Route $route): string
    {
        $method = $this->method($route);
        $priority = match (true) {
            $method === 'GET' => 0,
            in_array($method, ['PATCH', 'PUT'], true) => 1,
            $method === 'DELETE' => 4,
            str_ends_with($route->uri(), '/archive') => 3,
            default => 2,
        };

        return "{$priority}:{$method}:{$route->uri()}";
    }

    private function method(Route $route): string
    {
        return collect($route->methods())
            ->first(fn (string $method): bool => ! in_array($method, ['HEAD', 'OPTIONS'], true))
            ?? 'GET';
    }

    private function resolveUri(string $uri, string $method, PrivacySeeder $fixtures): string
    {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            fn (array $matches): string => $this->routeParameter(
                $uri,
                $matches[1],
                $method,
                $fixtures,
            ),
            $uri,
        ) ?? $uri;
    }

    private function routeParameter(
        string $uri,
        string $parameter,
        string $method,
        PrivacySeeder $fixtures,
    ): string {
        return match (true) {
            $uri === 'api/admin/partners/{id}' => (string) $fixtures->partner->id,
            $uri === 'api/admin/system-exercises/{systemExercise}/archive' => (string) $fixtures->archivableSystemExercise->id,
            $uri === 'api/admin/system-measure-templates/{systemMeasureTemplate}/archive' => (string) $fixtures->archivableSystemMeasureTemplate->id,
            $uri === 'api/company/invitations/{invite}' => (string) $fixtures->revocableInvite->id,
            $uri === 'api/company/surveys/{id}' && $method === 'DELETE' => (string) $fixtures->deletableSurvey->id,
            str_ends_with($uri, '/surveys/{id}/activate') => (string) $fixtures->draftSurvey->id,
            str_contains($uri, '/surveys/{id}') && $method !== 'GET' => (string) $fixtures->draftSurvey->id,
            str_contains($uri, '/surveys/{id}') => (string) $fixtures->survey->id,
            str_contains($uri, '/teams/{id}') && $method !== 'GET' => (string) $fixtures->mutableTeam->id,
            str_contains($uri, '/teams/{id}') => (string) $fixtures->team->id,
            $parameter === 'company' => (string) $fixtures->company->id,
            $parameter === 'teamId' => (string) $fixtures->team->id,
            $parameter === 'measure', str_contains($uri, '/measures/{id}') => (string) $fixtures->measure->id,
            $parameter === 'systemExercise' => (string) $fixtures->systemExercise->id,
            $parameter === 'systemMeasureTemplate' => (string) $fixtures->systemMeasureTemplate->id,
            $parameter === 'templateExercise' => (string) $fixtures->templateExercise->id,
            default => '999999999',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        string $method,
        string $uri,
        User $user,
        PrivacySeeder $fixtures,
    ): array {
        if (! $this->isSuccessActor($uri, $user)) {
            return [];
        }

        return match ("{$method} {$uri}") {
            'POST api/admin/companies' => [
                'name' => 'Synthetic Route Sweep Company',
                'slug' => 'synthetic-route-sweep-company',
            ],
            'PUT api/admin/companies/{company}' => [
                'name' => 'Privacy Test Company Updated',
            ],
            'POST api/admin/companies/{company}/invite-company-admin' => [
                'email' => 'new-company-admin@privacy.invalid',
            ],
            'PATCH api/admin/partners/{id}' => [
                'action' => 'approve',
            ],
            'PUT api/admin/points-config' => [
                'daily_checkin' => 10,
                'streak_7days' => 50,
                'streak_30days' => 200,
                'anamnesis_completed' => 100,
                'medical_document_upload' => 25,
                'measure_participation' => 20,
            ],
            'POST api/admin/system-exercises' => [
                'title' => 'Synthetic Route Sweep Exercise',
                'exerciseType' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
            ],
            'PATCH api/admin/system-exercises/{systemExercise}' => [
                'shortDescription' => 'Updated by privacy route sweep.',
            ],
            'POST api/admin/system-measure-templates' => [
                'title' => 'Synthetic Route Sweep Template',
            ],
            'PATCH api/admin/system-measure-templates/{systemMeasureTemplate}' => [
                'shortDescription' => 'Updated by privacy route sweep.',
            ],
            'POST api/admin/system-measure-templates/{systemMeasureTemplate}/exercises' => [
                'systemExerciseId' => $fixtures->attachableSystemExercise->id,
            ],
            'POST api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/reorder' => [
                'items' => $this->templateExerciseOrder($fixtures),
            ],
            'PATCH api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/{templateExercise}' => [
                'customTitle' => 'Synthetic route-sweep override',
            ],
            'POST api/company/invitations' => [
                'email' => 'new-employee@privacy.invalid',
                'role' => Role::EMPLOYEE->value,
                'teamId' => $fixtures->team->id,
            ],
            'POST api/company/measures' => [
                'title' => 'Synthetic route sweep measure',
                'category' => 'workshop',
                'description' => 'Synthetic measure created by the privacy route sweep.',
            ],
            'PATCH api/company/measures/{id}' => [
                'title' => 'Synthetic privacy measure updated',
            ],
            'POST api/company/surveys' => [
                'title' => 'Synthetic route sweep survey',
                'description' => 'Synthetic survey created by the privacy route sweep.',
                'isAnonymous' => true,
                'questions' => [[
                    'text' => 'Synthetic route sweep question',
                    'type' => 'SCALE',
                    'order' => 0,
                    'isRequired' => true,
                ]],
            ],
            'PATCH api/company/surveys/{id}' => [
                'title' => 'Synthetic editable privacy survey updated',
            ],
            'POST api/company/teams' => [
                'name' => 'Synthetic Route Sweep Team',
            ],
            'PUT api/company/teams/{id}' => [
                'name' => 'Synthetic Mutable Team Updated',
            ],
            default => [],
        };
    }

    private function isSuccessActor(string $uri, User $user): bool
    {
        if (str_starts_with($uri, 'api/admin/')) {
            return $user->hasRole(Role::ELYO_ADMIN);
        }

        return $user->hasRole(Role::COMPANY_ADMIN);
    }

    /**
     * @return list<array{id: int, sortOrder: int}>
     */
    private function templateExerciseOrder(PrivacySeeder $fixtures): array
    {
        return SystemMeasureTemplateExercise::query()
            ->where('system_measure_template_id', $fixtures->systemMeasureTemplate->id)
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn (SystemMeasureTemplateExercise $exercise, int $index): array => [
                'id' => $exercise->id,
                'sortOrder' => $index + 1,
            ])
            ->all();
    }
}
