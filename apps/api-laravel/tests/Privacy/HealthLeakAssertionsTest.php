<?php

namespace Tests\Privacy;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Tests\Support\HealthLeakAssertions;

class HealthLeakAssertionsTest extends TestCase
{
    use HealthLeakAssertions;

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function forbiddenKeyPayloads(): array
    {
        return [
            'energy' => [['data' => ['energy' => 4]], '$.data.energy'],
            'stress' => [['data' => ['stress' => 2]], '$.data.stress'],
            'lab marker key' => [['data' => ['markerKey' => 'ferritin']], '$.data.markerKey'],
            'measurement timestamp' => [['data' => ['measuredAt' => '2026-07-20']], '$.data.measuredAt'],
            'health subject key' => [['data' => ['healthSubjectId' => 'synthetic']], '$.data.healthSubjectId'],
            'raw text answer' => [['data' => ['textValue' => 'synthetic']], '$.data.textValue'],
            'raw answer text' => [['data' => ['answerText' => 'synthetic']], '$.data.answerText'],
            'singular wellbeing record' => [['data' => ['wellbeingEntry' => ['id' => 17]]], '$.data.wellbeingEntry'],
        ];
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function standaloneLabFields(): array
    {
        return [
            'unit' => ['unit', 'ng/ml'],
            'lower bound' => ['low', 30],
            'upper bound' => ['high', 300],
            'group' => ['group', 'synthetic'],
            'source' => ['source', 'manual'],
            'name' => ['name', 'Synthetic Ferritin'],
            'status' => ['status', 'below_range'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function contextualScoreKeys(): array
    {
        return [
            'health score' => ['healthScore'],
            'wellbeing score' => ['wellbeingScore'],
            'average score' => ['averageScore'],
            'overall score' => ['overallScore'],
            'score value' => ['scoreValue'],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidAggregateReleaseCounts(): array
    {
        return [
            'missing global counts' => [[]],
            'insufficient contributors' => [[
                'minRequired' => 10,
                'responseCount' => 5,
                'participation' => ['eligibleCount' => 10],
            ]],
            'insufficient eligible population' => [[
                'minRequired' => 10,
                'responseCount' => 10,
                'participation' => ['eligibleCount' => 9],
            ]],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function companyReportingEndpoints(): array
    {
        return [
            'dashboard' => ['/api/company/dashboard'],
            'reports' => ['/api/company/reports'],
            'survey results' => ['/api/company/surveys/17/results'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('forbiddenKeyPayloads')]
    public function test_catalog_key_patterns_are_rejected_at_the_offending_path(
        array $payload,
        string $expectedPath,
    ): void {
        $response = TestResponse::fromBaseResponse(new JsonResponse($payload));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage($expectedPath);

        $this->assertResponseHasNoHealthLeaks($response, '/api/company/new-endpoint');
    }

    public function test_mood_key_is_rejected_with_only_its_path_in_the_failure(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['mood' => 4],
        ]));

        try {
            $this->assertResponseHasNoHealthLeaks($response, '/api/company/new-endpoint');
            $this->fail('A company response containing mood unexpectedly passed.');
        } catch (ExpectationFailedException $exception) {
            $this->assertStringContainsString('$.data.mood', $exception->getMessage());
            $this->assertStringNotContainsString('"mood":4', $exception->getMessage());
        }
    }

    public function test_lab_value_and_unit_pair_is_rejected_at_the_object_path(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [['label' => 'synthetic', 'value' => 42.1, 'unit' => 'ng/ml']],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.0');

        $this->assertResponseHasNoHealthLeaks($response, '/api/admin/new-endpoint');
    }

    #[DataProvider('standaloneLabFields')]
    public function test_standalone_lab_fields_are_rejected_in_lab_context(string $key, mixed $value): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [$key => $value],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.'.$key);

        $this->assertResponseHasNoHealthLeaks($response, '/api/company/lab-summary');
    }

    public function test_standalone_lab_field_is_rejected_when_json_path_establishes_context(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['labSummary' => ['unit' => 'ng/ml']],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.labSummary.unit');

        $this->assertResponseHasNoHealthLeaks($response, '/api/company/new-endpoint');
    }

    public function test_standalone_lab_field_is_rejected_when_sibling_key_establishes_context(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [
                'markerName' => 'Synthetic Ferritin',
                'unit' => 'ng/ml',
            ],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.unit');

        $this->assertResponseHasNoHealthLeaks($response, '/api/company/new-endpoint');
    }

    public function test_known_health_subject_is_rejected_even_under_a_generic_key(): void
    {
        $subjectId = '01J5KQ9Z8Y7X6W5V4T3S2R1Q0P';
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['id' => $subjectId],
        ]));

        try {
            $this->assertResponseHasNoHealthLeaks(
                $response,
                '/api/company/new-endpoint',
                [$subjectId],
            );
            $this->fail('A known health subject unexpectedly passed.');
        } catch (ExpectationFailedException $exception) {
            $this->assertStringContainsString('$.data.id', $exception->getMessage());
            $this->assertStringNotContainsString($subjectId, $exception->getMessage());
        }
    }

    public function test_score_is_rejected_only_in_a_health_context(): void
    {
        $healthResponse = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['score' => 4.2],
        ]));

        try {
            $this->assertResponseHasNoHealthLeaks($healthResponse, '/api/company/wellbeing');
            $this->fail('A score in a wellbeing context unexpectedly passed.');
        } catch (ExpectationFailedException $exception) {
            $this->assertStringContainsString('$.data.score', $exception->getMessage());
        }

        $identityResponse = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['score' => 4.2],
        ]));
        $this->assertResponseHasNoHealthLeaks($identityResponse, '/api/admin/system-exercises');
    }

    #[DataProvider('contextualScoreKeys')]
    public function test_score_variants_are_rejected_on_company_reporting_surfaces(string $key): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [$key => 4.2],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.'.$key);

        $this->assertResponseHasNoHealthLeaks($response, '/api/company/dashboard');
    }

    public function test_generic_text_is_rejected_inside_an_answer_collection(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [
                'answers' => [[
                    'text' => 'Synthetic sensitive answer',
                ]],
            ],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.answers.0.text');

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/surveys/17/results',
        );
    }

    public function test_note_is_rejected_in_a_wellbeing_context(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['note' => 'Synthetic sensitive note'],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.note');

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/wellbeing',
        );
    }

    #[DataProvider('companyReportingEndpoints')]
    public function test_score_is_rejected_on_company_reporting_surfaces(string $endpoint): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['score' => 4.2],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.score');

        $this->assertResponseHasNoHealthLeaks($response, $endpoint);
    }

    #[DataProvider('companyReportingEndpoints')]
    public function test_value_is_rejected_on_company_reporting_surfaces(string $endpoint): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['value' => 4.2],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.value');

        $this->assertResponseHasNoHealthLeaks($response, $endpoint);
    }

    public function test_survey_distribution_allowlist_rejects_a_suppressed_error_payload(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [
                'isAboveThreshold' => false,
                'minRequired' => 10,
                'responseCount' => 10,
                'participation' => ['eligibleCount' => 10],
                'questions' => [[
                    'type' => 'SCALE',
                    'isSuppressed' => true,
                    'distribution' => [[
                        'value' => 4,
                        'count' => 5,
                    ]],
                ]],
            ],
        ], 403));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.questions.0.distribution.0.value');

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/surveys/{id}/results',
        );
    }

    public function test_survey_distribution_allowlist_rejects_a_small_bucket(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [
                'isAboveThreshold' => true,
                'minRequired' => 10,
                'responseCount' => 10,
                'participation' => ['eligibleCount' => 10],
                'questions' => [[
                    'type' => 'SCALE',
                    'isSuppressed' => false,
                    'distribution' => [[
                        'value' => 4,
                        'count' => 4,
                    ]],
                ]],
            ],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.questions.0.distribution.0.value');

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/surveys/{id}/results',
        );
    }

    public function test_survey_distribution_allowlist_accepts_a_released_bucket(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [
                'isAboveThreshold' => true,
                'minRequired' => 10,
                'responseCount' => 10,
                'participation' => ['eligibleCount' => 10],
                'questions' => [[
                    'type' => 'SCALE',
                    'isSuppressed' => false,
                    'distribution' => [[
                        'value' => 4,
                        'count' => 5,
                    ]],
                ]],
            ],
        ]));

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/surveys/{id}/results',
        );
        $this->addToAssertionCount(1);
    }

    /**
     * @param  array<string, mixed>  $releaseCounts
     */
    #[DataProvider('invalidAggregateReleaseCounts')]
    public function test_survey_distribution_allowlist_rejects_invalid_global_release_counts(
        array $releaseCounts,
    ): void {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => array_merge([
                'isAboveThreshold' => true,
                'questions' => [[
                    'type' => 'SCALE',
                    'isSuppressed' => false,
                    'distribution' => [[
                        'value' => 4,
                        'count' => 5,
                    ]],
                ]],
            ], $releaseCounts),
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.questions.0.distribution.0.value');

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/surveys/{id}/results',
        );
    }

    public function test_survey_distribution_allowlist_rejects_a_non_scale_question(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [
                'isAboveThreshold' => true,
                'minRequired' => 10,
                'responseCount' => 10,
                'participation' => ['eligibleCount' => 10],
                'questions' => [[
                    'type' => 'TEXT',
                    'isSuppressed' => false,
                    'distribution' => [[
                        'value' => 4,
                        'count' => 5,
                    ]],
                ]],
            ],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.questions.0.distribution.0.value');

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/company/surveys/{id}/results',
        );
    }

    public function test_ulid_is_rejected_at_a_health_related_path(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['healthRecord' => ['id' => '01J5KQ9Z8Y7X6W5V4T3S2R1Q0P']],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.healthRecord.id');

        $this->assertResponseHasNoHealthLeaks($response, '/api/company/new-endpoint');
    }

    public function test_ulid_is_rejected_at_a_generic_path_in_company_context(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => ['id' => '01J5KQ9Z8Y7X6W5V4T3S2R1Q0P'],
        ]));

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('$.data.id');

        $this->assertResponseHasNoHealthLeaks($response, '/api/company/users');
    }

    public function test_identity_only_payload_is_allowed(): void
    {
        $response = TestResponse::fromBaseResponse(new JsonResponse([
            'data' => [
                'id' => 17,
                'name' => 'Synthetic company',
                'status' => 'active',
            ],
        ]));

        $this->assertResponseHasNoHealthLeaks($response, '/api/admin/companies/17');
        $this->addToAssertionCount(1);
    }

    public function test_no_content_response_is_allowed_without_json_parsing(): void
    {
        $response = TestResponse::fromBaseResponse(new Response(status: 204));

        $this->assertResponseHasNoHealthLeaks(
            $response,
            '/api/admin/system-measure-templates/1/exercises/1',
        );
        $this->addToAssertionCount(1);
    }

    public function test_no_content_failure_reports_only_the_path_not_the_payload(): void
    {
        $response = TestResponse::fromBaseResponse(new Response(
            'sensitive-payload-value',
            205,
        ));

        try {
            $this->assertResponseHasNoHealthLeaks(
                $response,
                '/api/company/dashboard',
            );
            $this->fail('A no-content response containing a payload unexpectedly passed.');
        } catch (ExpectationFailedException $exception) {
            $this->assertStringContainsString('$', $exception->getMessage());
            $this->assertStringNotContainsString(
                'sensitive-payload-value',
                $exception->getMessage(),
            );
        }
    }
}
