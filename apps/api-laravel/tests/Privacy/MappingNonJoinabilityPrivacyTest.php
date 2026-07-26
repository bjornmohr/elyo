<?php

namespace Tests\Privacy;

use App\Models\Health\HealthSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use Tests\Boundary\BoundaryTestCase;

class MappingNonJoinabilityPrivacyTest extends BoundaryTestCase
{
    public function test_standard_identity_connection_cannot_query_subject_mappings(): void
    {
        $connection = $this->boundaryConnection(
            'privacy_identity_to_mapping',
            'mapping',
            'elyo_identity_rt',
            (string) env('DB_IDENTITY_PASSWORD'),
        );

        $this->assertDatabaseOperationDenied(
            fn () => $connection->table('subject_mappings')->count(),
            'permission denied',
            'Identity runtime unexpectedly queried subject mappings.',
        );
    }

    public function test_user_has_no_eloquent_relation_path_to_a_health_model(): void
    {
        $this->assertSame(
            [],
            $this->healthRelationMethodsDeclaredOn(new User),
            'User must not expose an Eloquent relation path to health-domain models.',
        );
    }

    public function test_relation_guard_detects_an_untyped_health_relation(): void
    {
        $userWithUntypedLeak = new class extends User
        {
            public function leakedHealthSubjects()
            {
                return $this->hasMany(HealthSubject::class, 'health_subject_id');
            }
        };

        $this->assertSame(
            ['leakedHealthSubjects()'],
            $this->healthRelationMethodsDeclaredOn($userWithUntypedLeak),
        );
    }

    /**
     * @return list<string>
     */
    private function healthRelationMethodsDeclaredOn(User $user): array
    {
        $declaredClass = $user::class;
        $violations = [];

        foreach ((new ReflectionClass($declaredClass))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->getDeclaringClass()->getName() !== $declaredClass
                || $method->getNumberOfRequiredParameters() > 0
                || $method->isStatic()
            ) {
                continue;
            }

            $result = $method->invoke($user);

            if (! $result instanceof Relation) {
                continue;
            }

            $related = $result->getRelated();

            if ($related instanceof HealthSubject || str_starts_with($related::class, 'App\\Models\\Health\\')) {
                $violations[] = $method->getName().'()';
            }
        }

        return $violations;
    }
}
