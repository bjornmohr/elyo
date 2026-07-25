<?php

namespace Tests\Unit\Support;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Support\AssertsNoWellbeingValues;
use Tests\TestCase;

class AssertsNoWellbeingValuesTest extends TestCase
{
    use AssertsNoWellbeingValues;

    public function test_assertion_rejects_an_unexpected_numeric_value_under_a_generic_field_name(): void
    {
        $response = TestResponse::fromBaseResponse(response()->json([
            'value' => 4.8,
        ]));

        $this->expectException(ExpectationFailedException::class);

        $this->assertResponseHasNoWellbeingValues($response);
    }

    public function test_assertion_rejects_an_unexpected_numeric_string_under_a_generic_field_name(): void
    {
        $response = TestResponse::fromBaseResponse(response()->json([
            'value' => '4.8',
        ]));

        $this->expectException(ExpectationFailedException::class);

        $this->assertResponseHasNoWellbeingValues($response);
    }

    public function test_assertion_allows_an_explicitly_allowlisted_identity_numeric_string(): void
    {
        $response = TestResponse::fromBaseResponse(response()->json([
            'teamId' => '42',
        ]));

        $this->assertResponseHasNoWellbeingValues($response, ['$.teamId']);
    }
}
