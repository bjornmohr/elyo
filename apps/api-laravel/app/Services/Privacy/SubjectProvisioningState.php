<?php

namespace App\Services\Privacy;

enum SubjectProvisioningState: string
{
    case MISSING = 'MISSING';
    case ACTIVE = 'ACTIVE';
    case REVOKED = 'REVOKED';
}
