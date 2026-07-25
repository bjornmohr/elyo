<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class ReportingPendingResource extends JsonResource
{
    public const STATUS = 'reporting_pending';

    private const LEGACY_FIELDS = [
        'isAboveThreshold',
        'responseCount',
    ];

    /**
     * @param  array<string, null>  $legacyFields
     */
    public function __construct(array $legacyFields = [])
    {
        foreach ($legacyFields as $field => $value) {
            if (! in_array($field, self::LEGACY_FIELDS, true) || $value !== null) {
                throw new InvalidArgumentException("Reporting-pending legacy field [{$field}] is not allowed.");
            }
        }

        parent::__construct($legacyFields);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => self::STATUS,
            'data' => null,
            ...$this->resource,
        ];
    }
}
