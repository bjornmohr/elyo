<?php

namespace App\Services\Privacy;

enum MappingActorType: string
{
    case REGISTRATION_WORKFLOW = 'registration-workflow';
    case EMPLOYEE_SELF_SERVICE = 'employee-self-service';
    case PRIVACY_ADMIN = 'privacy-admin';
    case REPORTING_WORKER = 'reporting-worker';
}
