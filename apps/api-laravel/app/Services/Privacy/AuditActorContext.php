<?php

namespace App\Services\Privacy;

final readonly class AuditActorContext
{
    private function __construct(
        public MappingActorType $type,
        public MappingRuntime $runtime,
        public AuditActorRole $role,
    ) {}

    public static function registrationWorkflow(): self
    {
        return new self(
            MappingActorType::REGISTRATION_WORKFLOW,
            MappingRuntime::IDENTITY_API,
            AuditActorRole::SYSTEM,
        );
    }

    public static function employeeSelfService(): self
    {
        return new self(
            MappingActorType::EMPLOYEE_SELF_SERVICE,
            MappingRuntime::EMPLOYEE_HEALTH_API,
            AuditActorRole::EMPLOYEE,
        );
    }

    public static function privacyAdmin(): self
    {
        return new self(
            MappingActorType::PRIVACY_ADMIN,
            MappingRuntime::PRIVACY_ADMIN,
            AuditActorRole::PRIVACY_ADMIN,
        );
    }

    public static function reportingWorker(): self
    {
        return new self(
            MappingActorType::REPORTING_WORKER,
            MappingRuntime::REPORTING_WORKER,
            AuditActorRole::REPORTING_WORKER,
        );
    }

    /**
     * @return array{type: string, runtime: string, role: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'runtime' => $this->runtime->value,
            'role' => $this->role->value,
        ];
    }
}
