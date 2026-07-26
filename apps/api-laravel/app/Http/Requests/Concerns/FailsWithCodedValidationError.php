<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Renders validation failures in the coded error envelope required by
 * `docs/ai-context/api-contract-rules.md`, instead of Laravel's default
 * `{message, errors}` shape.
 */
trait FailsWithCodedValidationError
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }
}
