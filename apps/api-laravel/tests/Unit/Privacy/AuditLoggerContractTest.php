<?php

namespace Tests\Unit\Privacy;

use App\Services\Privacy\AuditLoggerContract;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AuditLoggerContractTest extends TestCase
{
    public function test_audit_contract_uses_typed_operation_and_actor_context(): void
    {
        $method = new ReflectionMethod(AuditLoggerContract::class, 'logMappingOperation');
        $parameters = $method->getParameters();

        $this->assertSame(
            'App\Services\Privacy\MappingOperation',
            $parameters[0]->getType()?->getName(),
        );
        $this->assertSame(
            'App\Services\Privacy\AuditActorContext',
            $parameters[2]->getType()?->getName(),
        );
    }
}
