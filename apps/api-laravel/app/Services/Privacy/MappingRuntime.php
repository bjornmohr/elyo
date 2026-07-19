<?php

namespace App\Services\Privacy;

enum MappingRuntime: string
{
    case IDENTITY_API = 'identity-api';
    case EMPLOYEE_HEALTH_API = 'employee-health-api';
    case PRIVACY_ADMIN = 'privacy-admin';
    case REPORTING_WORKER = 'reporting-worker';
}
