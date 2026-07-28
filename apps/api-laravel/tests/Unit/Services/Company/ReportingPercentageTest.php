<?php

namespace Tests\Unit\Services\Company;

use App\Services\Company\ReportingPercentage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReportingPercentageTest extends TestCase
{
    /**
     * @return array<string, array{int, int, int}>
     */
    public static function percentages(): array
    {
        return [
            'exact step is unchanged' => [5, 10, 50],
            'rounds up to the nearest step' => [11, 12, 90],
            'rounds down to the nearest step' => [6, 11, 55],
            'full participation stays at one hundred' => [12, 12, 100],
            'no contributors stays at zero' => [0, 12, 0],
            'zero total does not divide' => [0, 0, 0],
            'negative total does not divide' => [3, -1, 0],
        ];
    }

    #[DataProvider('percentages')]
    public function test_it_rounds_to_five_point_steps(int $count, int $total, int $expected): void
    {
        $this->assertSame($expected, ReportingPercentage::of($count, $total));
    }

    public function test_it_never_returns_a_value_outside_a_five_point_step(): void
    {
        for ($total = 1; $total <= 40; $total++) {
            for ($count = 0; $count <= $total; $count++) {
                $percentage = ReportingPercentage::of($count, $total);

                $this->assertSame(0, $percentage % 5, "Non-step percentage for {$count}/{$total}.");
                $this->assertGreaterThanOrEqual(0, $percentage);
                $this->assertLessThanOrEqual(100, $percentage);
            }
        }
    }
}
