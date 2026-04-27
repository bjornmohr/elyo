<?php

namespace App\Enums;

enum Role: string
{
    case COMPANY_ADMIN = 'COMPANY_ADMIN';
    case COMPANY_MANAGER = 'COMPANY_MANAGER';
    case EMPLOYEE = 'EMPLOYEE';
    case ELYO_ADMIN = 'ELYO_ADMIN';
}
