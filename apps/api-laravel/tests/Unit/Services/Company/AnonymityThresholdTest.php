<?php

namespace Tests\Unit\Services\Company;

use App\Services\Company\AnonymityThreshold;
use PHPUnit\Framework\TestCase;

class AnonymityThresholdTest extends TestCase
{
    public function test_it_uses_the_company_threshold_when_configured(): void
    {
        $this->assertSame(3, AnonymityThreshold::resolve(3));
    }

    public function test_it_uses_the_default_threshold_when_not_configured(): void
    {
        $this->assertSame(5, AnonymityThreshold::resolve(null));
    }
}
