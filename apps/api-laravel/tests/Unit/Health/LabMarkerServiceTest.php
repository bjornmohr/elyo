<?php

namespace Tests\Unit\Health;

use App\Models\Health\LabMarker;
use App\Services\Health\LabMarkerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LabMarkerServiceTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function statusValues(): array
    {
        return [
            'below the lower bound' => ['9.9999', LabMarkerService::STATUS_BELOW_RANGE],
            'on the lower bound' => ['10.0000', LabMarkerService::STATUS_IN_RANGE],
            'between both bounds' => ['15.0000', LabMarkerService::STATUS_IN_RANGE],
            'on the upper bound' => ['20.0000', LabMarkerService::STATUS_IN_RANGE],
            'above the upper bound' => ['20.0001', LabMarkerService::STATUS_ABOVE_RANGE],
        ];
    }

    #[DataProvider('statusValues')]
    public function test_status_is_derived_from_the_catalog_orientation_range(
        string $value,
        string $expectedStatus,
    ): void {
        $marker = new LabMarker([
            'low' => '10.0000',
            'high' => '20.0000',
        ]);

        $this->assertSame(
            $expectedStatus,
            (new LabMarkerService)->deriveStatus($marker, $value),
        );
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string, 3: string}>
     */
    public static function openEndedRanges(): array
    {
        return [
            'no lower bound, value under the upper bound' => [null, '20.0000', '0.0000', LabMarkerService::STATUS_IN_RANGE],
            'no lower bound, value over the upper bound' => [null, '20.0000', '20.0001', LabMarkerService::STATUS_ABOVE_RANGE],
            'no upper bound, value over the lower bound' => ['10.0000', null, '9999.0000', LabMarkerService::STATUS_IN_RANGE],
            'no upper bound, value under the lower bound' => ['10.0000', null, '9.9999', LabMarkerService::STATUS_BELOW_RANGE],
            'no bounds at all' => [null, null, '12345.0000', LabMarkerService::STATUS_IN_RANGE],
        ];
    }

    #[DataProvider('openEndedRanges')]
    public function test_status_ignores_bounds_the_catalog_leaves_open(
        ?string $low,
        ?string $high,
        string $value,
        string $expectedStatus,
    ): void {
        $marker = new LabMarker(['low' => $low, 'high' => $high]);

        $this->assertSame(
            $expectedStatus,
            (new LabMarkerService)->deriveStatus($marker, $value),
        );
    }
}
