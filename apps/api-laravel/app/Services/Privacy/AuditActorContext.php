<?php

namespace App\Services\Privacy;

final readonly class AuditActorContext
{
    private function __construct(
        public MappingActorType $type,
        public MappingRuntime $runtime,
    ) {}

    public static function registrationWorkflow(): self
    {
        return new self(MappingActorType::REGISTRATION_WORKFLOW, MappingRuntime::IDENTITY_API);
    }

    public static function employeeSelfService(): self
    {
        return new self(MappingActorType::EMPLOYEE_SELF_SERVICE, MappingRuntime::EMPLOYEE_HEALTH_API);
    }

    public static function privacyAdmin(): self
    {
        return new self(MappingActorType::PRIVACY_ADMIN, MappingRuntime::PRIVACY_ADMIN);
    }

    public static function reportingWorker(): self
    {
        return new self(MappingActorType::REPORTING_WORKER, MappingRuntime::REPORTING_WORKER);
    }

    /**
     * @return array{type: string, runtime: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'runtime' => $this->runtime->value,
        ];
    }
}
