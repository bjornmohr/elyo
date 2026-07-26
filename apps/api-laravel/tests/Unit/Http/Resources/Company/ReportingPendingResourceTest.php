<?php

namespace Tests\Unit\Http\Resources\Company;

use App\Http\Resources\Company\ReportingPendingResource;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReportingPendingResourceTest extends TestCase
{
    public function test_reserved_contract_fields_cannot_be_overridden(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReportingPendingResource(['status' => null]);
    }
}
