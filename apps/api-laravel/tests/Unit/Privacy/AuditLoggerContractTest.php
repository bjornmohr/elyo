<?php

namespace Tests\Unit\Privacy;

use App\Services\Privacy\AuditActorContext;
use App\Services\Privacy\AuditActorRole;
use App\Services\Privacy\AuditLoggerContract;
use App\Services\Privacy\AuditOutcome;
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
        $this->assertSame(AuditOutcome::class, $parameters[4]->getType()?->getName());

        $backfill = new ReflectionMethod(AuditLoggerContract::class, 'logProvisioningBackfill');
        $this->assertSame(
            AuditOutcome::class,
            $backfill->getParameters()[1]->getType()?->getName(),
        );
        $this->assertInstanceOf(
            AuditActorRole::class,
            AuditActorContext::employeeSelfService()->role,
        );
    }
}
