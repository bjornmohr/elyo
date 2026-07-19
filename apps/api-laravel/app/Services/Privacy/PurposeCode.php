<?php

namespace App\Services\Privacy;

enum PurposeCode: string
{
    case PROVISIONING = 'PROVISIONING';
    case HEALTH_SELF_READ = 'HEALTH_SELF_READ';
    case HEALTH_SELF_WRITE = 'HEALTH_SELF_WRITE';
    case REVOCATION = 'REVOCATION';
    case REPORTING = 'REPORTING';
    case DSR = 'DSR';
}
