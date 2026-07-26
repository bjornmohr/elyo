<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\Concerns\FailsWithCodedValidationError;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pagination parameters for the per-marker history read (ELYO-102 §1.3).
 *
 * `page`/`perPage` mirrors the admin list endpoints; `perPage` is capped so a
 * single request cannot pull an unbounded health history.
 */
class LabMarkerHistoryRequest extends FormRequest
{
    use FailsWithCodedValidationError;

    public const DEFAULT_PER_PAGE = 25;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['perPage'] ?? self::DEFAULT_PER_PAGE);
    }
}
