<?php

namespace App\Enums;

enum PartnerVerificationStatus: string
{
    case PENDING_DOCS = 'PENDING_DOCS';
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case VERIFIED = 'VERIFIED';
    case SUSPENDED = 'SUSPENDED';
    case REJECTED = 'REJECTED';
}
