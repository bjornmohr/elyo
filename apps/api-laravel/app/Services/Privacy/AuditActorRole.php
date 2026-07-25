<?php

namespace App\Services\Privacy;

enum AuditActorRole: string
{
    case SYSTEM = 'system';
    case EMPLOYEE = 'employee';
    case PRIVACY_ADMIN = 'privacy-admin';
    case REPORTING_WORKER = 'reporting-worker';
}
