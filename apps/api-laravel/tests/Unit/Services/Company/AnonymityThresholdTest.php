<?php

namespace Tests\Unit\Services\Company;

use App\Services\Company\AnonymityThreshold;
use PHPUnit\Framework\TestCase;

class AnonymityThresholdTest extends TestCase
{
    public function test_it_enforces_the_platform_minimum_when_company_threshold_is_lower(): void
    {
        $this->assertSame(10, AnonymityThreshold::resolve(3));
    }

    public function test_it_uses_the_platform_minimum_when_not_configured(): void
    {
        $this->assertSame(10, AnonymityThreshold::resolve(null));
    }

    public function test_it_preserves_a_stricter_company_threshold(): void
    {
        $this->assertSame(20, AnonymityThreshold::resolve(20));
    }

    public function test_it_uses_five_as_the_reporting_category_minimum(): void
    {
        $this->assertSame(5, AnonymityThreshold::categoryMinimum());
    }
}
