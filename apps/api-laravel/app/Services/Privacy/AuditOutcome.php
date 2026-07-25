<?php

namespace App\Services\Privacy;

enum AuditOutcome: string
{
    case SUCCESS = 'success';
    case DENIED = 'denied';
    case FAILED = 'failed';
}
